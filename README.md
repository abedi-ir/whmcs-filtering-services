# WHMCS Service Filtering

## Introduction:

This PHP code provides a function to filter services within a WHMCS environment based on their status. This enhances user experience by allowing clients to quickly access specific services, reducing confusion and improving service retention.

## Default Behavior:

By default, only active and suspended services are displayed.
To view all services (including terminated and cancelled), include the status parameter with a value of `all` in the URL.

## Functionality:

1. Filters services based on the status parameter in the URL.
2. Available statuses: exists, active, suspended, terminated, cancelled.
3. exists displays both active and suspended services.
4. Integrates with the WHMCS client area to display filtered services.

## Prerequisites

* Installed and configured WHMCS
* Basic PHP and HTML knowledge

## Installation:

1. Place the code within the includes directory of your WHMCS installation.
2. To filter services by status, add the status parameter to the URL (e.g., `https://whmcs-panel-domain.com/clientarea.php?action=services&status=terminated` displays only terminated services.).
3. Modify the `clientareaproducts.tpl` to implement additional filtering options or display methods (e.
g., search select, separate tabs).

By using this code, you can significantly improve the service management experience for your clients within the WHMCS platform.
