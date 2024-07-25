<?php

if (!defined('WHMCS'))
	die('You cannot access this file directly.');

/**
 * @param string $val
 * @return string
 */
function getRawStatus($val)
{
	$val = strtolower($val);
	$val = str_replace(" ", "", $val);
	$val = str_replace("-", "", $val);
	return $val;
}

function filter_services_by_status($vars)
{
	$queryStatus = strtolower($_GET['status'] ?? 'exists');

	if ('all' == $queryStatus or !in_array($queryStatus, ['exists', 'active', 'suspended', 'terminated', 'cancelled'])) {
		$queryStatus = '';
	}

	$accounts = $vars['services'];
	if ($queryStatus) {
		global $whmcs;
		$legacyClient = new WHMCS\Client($vars['client']);
		$table = "tblhosting";
		$fields = "COUNT(*)";
		$where = "userid='" . db_escape_string($legacyClient->getID()) . "'";
		$q = $vars['q'] ?? '';
		if ($q) {
			$q = preg_replace("/[^a-z0-9-.]/", "", strtolower($q));
			$where .= " AND domain LIKE '%" . db_escape_string($q) . "%'";
		}
		if ($module = $whmcs->get_req_var("module")) {
			$where .= " AND tblproducts.servertype='" . db_escape_string($module) . "'";
		}
		if ('exists' == $queryStatus) {
			$where .= " AND tblhosting.domainstatus IN ('Active', 'Suspended')";
		} else {
			$where .= " AND tblhosting.domainstatus = '" . ucfirst($queryStatus) . "'";
		}

		$innerjoin = "tblproducts ON tblproducts.id=tblhosting.packageid INNER JOIN tblproductgroups ON tblproductgroups.id=tblproducts.gid";
		$result = select_query($table, $fields, $where, "", "", "", $innerjoin);
		$data = mysql_fetch_array($result);
		$numitems = $data[0];
		$accounts = [];
		if ($numitems) {
			[$orderby, $sort, $limit] = clientAreaTableInit("prod", "product", "ASC", $numitems);
			if ($orderby == "price") {
				$orderby = "amount";
			} else {
				if ($orderby == "billingcycle") {
					$orderby = "billingcycle";
				} else {
					if ($orderby == "nextduedate") {
						$orderby = "nextduedate";
					} else {
						if ($orderby == "status") {
							$orderby = "domainstatus";
						} else {
							$orderby = "domain` " . $sort . ",`tblproducts`.`name";
						}
					}
				}
			}
			$clientSslStatuses = WHMCS\Domain\Ssl\Status::where("user_id", $legacyClient->getID())->get();
			$productCache = array();

			$fields = "tblhosting.*,tblproductgroups.id AS group_id,tblproducts.name as product_name,tblproducts.tax," . "tblproductgroups.name as group_name,tblproducts.servertype,tblproducts.type";
			$result = select_query($table, $fields, $where, $orderby, $sort, $limit, $innerjoin);

			while ($data = mysql_fetch_array($result)) {
				$id = $data["id"];
				$productId = $data["packageid"];
				$regdate = $data["regdate"];
				$domain = $data["domain"];
				$firstpaymentamount = $data["firstpaymentamount"];
				$recurringamount = $data["amount"];
				$nextduedate = $data["nextduedate"];
				$billingcycle = $data["billingcycle"];
				$status = $data["domainstatus"];
				$tax = $data["tax"];
				$server = $data["server"];
				$username = $data["username"];
				$module = $data["servertype"];
				if (!isset($productCache["downloads"][$productId])) {
					$productCache["downloads"][$productId] = WHMCS\Product\Product::find($productId)->getDownloadIds();
				}
				if (!isset($productCache["upgrades"][$productId])) {
					$productCache["upgrades"][$productId] = WHMCS\Product\Product::find($productId)->getUpgradeProductIds();
				}
				if (!isset($productCache["groupNames"][$data["group_id"]])) {
					$productCache["groupNames"][$data["group_id"]] = WHMCS\Product\Group::getGroupName($data["group_id"], $data["group_name"]);
				}
				if (!isset($productCache["productNames"][$data["packageid"]])) {
					$productCache["productNames"][$data["packageid"]] = WHMCS\Product\Product::getProductName($data["packageid"], $data["product_name"]);
				}
				if (0 < $server && !isset($productCache["servers"][$server])) {
					$productCache["servers"][$server] = get_query_vals("tblservers", "", array("id" => $server));
				}
				$downloads = $productCache["downloads"][$productId];
				$upgradepackages = $productCache["upgrades"][$productId];
				$productgroup = $productCache["groupNames"][$data["group_id"]];
				$productname = $productCache["productNames"][$data["packageid"]];
				$serverarray = 0 < $server ? $productCache["servers"][$server] : array();
				$normalisedRegDate = $regdate;
				$regdate = fromMySQLDate($regdate, 0, 1, "-");
				$normalisedNextDueDate = $nextduedate;
				$nextduedate = fromMySQLDate($nextduedate, 0, 1, "-");
				$langbillingcycle = getRawStatus($billingcycle);
				$rawstatus = getRawStatus($status);
				$legacyClassTplVar = $status;
				if (!in_array($legacyClassTplVar, array("Active", "Completed", "Pending", "Suspended"))) {
					$legacyClassTplVar = "Terminated";
				}
				$amount = $billingcycle == "One Time" ? $firstpaymentamount : $recurringamount;
				$isDomain = str_replace(".", "", $domain) != $domain;
				if ($data["type"] == "other") {
					$isDomain = false;
				}
				$isActive = in_array($status, array("Active", "Completed"));
				$sslStatus = NULL;
				if ($isDomain && $isActive) {
					$sslStatus = $clientSslStatuses->where("domain_name", $domain)->first();
					if (is_null($sslStatus)) {
						$sslStatus = WHMCS\Domain\Ssl\Status::factory($legacyClient->getID(), $domain);
					}
				}

				$accounts[] = array("id" => $id, "regdate" => $regdate, "normalisedRegDate" => $normalisedRegDate, "group" => $productgroup, "product" => $productname, "module" => $module, "server" => $serverarray, "domain" => $domain, "firstpaymentamount" => formatCurrency($firstpaymentamount), "recurringamount" => formatCurrency($recurringamount), "amountnum" => $amount, "amount" => formatCurrency($amount), "nextduedate" => $nextduedate, "normalisedNextDueDate" => $normalisedNextDueDate, "billingcycle" => Lang::trans("orderpaymentterm" . $langbillingcycle), "username" => $username, "status" => $status, "statusClass" => WHMCS\View\Helper::generateCssFriendlyClassName($status), "statustext" => Lang::trans("clientarea" . $rawstatus), "rawstatus" => $rawstatus, "class" => strtolower($legacyClassTplVar), "addons" => get_query_val("tblhostingaddons", "id", array("hostingid" => $id), "id", "DESC") ? true : false, "packagesupgrade" => 0 < count($upgradepackages), "downloads" => 0 < count($downloads), "showcancelbutton" => (bool) WHMCS\Config\Setting::getValue("ShowCancellationButton"), "sslStatus" => $sslStatus, "isActive" => $isActive);
			}
		}
	}

	return [
		'services' => $accounts,
		'activeStatus' => $queryStatus ?: 'all',
	];
}

add_hook('ClientAreaPageProductsServices', 1, 'filter_services_by_status');
