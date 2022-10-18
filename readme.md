# Cookies banner

## Installation
- PHP: include_once('/cookies-banner/ajax/config.php');
- Javascript/jQuery required:
```
    var options = {
        parentDir: "cookies-banner/",
        settingsButton: "a.cookies-settings"
    }
    $("body").cookies( options ).showPanel(); 
```
- extensions/cookies/cookies.css
- extensions/cookies/cookies.js
- PHP: (( $cookies->confirmed('{category}') ) ? "'granted'" : "'denied'" )
- jQuery: if( $.cookies().isConfirmed({category}) ){ console.log('confirmed') }