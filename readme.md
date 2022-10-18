
# Cookies banner

## Installation

### 1. Update config file

in file **/cookie-banner/ajax/config.php** change following variables:
```php
$COOKIES_CFG['DB_HOST'] =  '{mysql_hostname}';
$COOKIES_CFG['DB_PORT'] =  '{mysql_port}';
$COOKIES_CFG['DB_USER'] =  '{mysql_username}';
$COOKIES_CFG['DB_PASS'] =  '{mysql_password}';
$COOKIES_CFG['DB_NAME'] =  '{mysql_database}';
$COOKIES_CFG['DB_PREFIX'] =  '{tables_prefix}';
```
### 2. Include files into code

#### PHP
before HTML code
```php
include_once('/cookies-banner/ajax/config.php');
```

#### HTML
```html
<head>
...
<link rel="stylesheet" href="{path_to_plugin}/cookies.css" />
</head>
<body>
...
<script type="text/javascript" src="{path_to_plugin}/jquery-3.3.1.min.js"></script>
<script type="text/javascript" src="{path_to_plugin}/cookies.js"></script>
<script type="text/javascript">
var cookiesOptions = {
  parentDir: "{path_to_plugin}/",
  settingsButton: "a.cookies-settings"
}
$("body").cookies( cookiesOptions ).showPanel();
</script>
</body>
```

## Check if category confirmed

**Default categories**
- necessary 
- preferences
- statistics
- marketing

### PHP
```PHP
if( $cookies->confirmed('category') ){
	echo 'category confirmed';
}
```