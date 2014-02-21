web2printer-php
===============
Printer friendly webpages on the fly.
Virtually every web page contain information's that is only usable while browsing the website. In contrast importation informations are lost while printing, Hyperlinks for example.
Web2printer closes this gap since 2001 and transforms every web page in a printer-friendly manner.
Web2Printer is the first and most powerful printer-friendly OSS today.
Prerequisites
-------------
PHP 5.3
Reference
---------
Homepage: http://phpshaper.com

E-Mail:   info (at) phpshaper.com

GITHUB:  https://github.com/phpshaper/web2printer-php

Examplepage : http://phpshaper.com/easyblog/2014/01/29/51-print-friendly-webpages-php.html
Beispielseite: http://phpshaper.com/easyblog/2014/01/29/56-print-friendly-webpages-php-de.html

Functions
---------
Web2Printer works online, real-time. There is no need to define a second, "hidden" page. Just put a hyperlink in your document. Web2Printer generates a meaningful page footer, to preserves the origin and navigation information.
The footer contains:
* The page url
* content of the author and copyright meta tags
* a hyperlink cross reference
* a image cross reference
* images replaced with the alt="" tag content
Put the relevant page content with in to two html comments:
    <!-- web2printer:start -->
    every thing between this comments is transformed in a printable manner
    <!-- web2printer:end -->
Parameters
----------
Web2printer accepts get and post parameters. All parameters are optional and validated before they are processed.

|parameter|value |meaning                                                               |constraint                            |
|---------|-----:|---------------------------------------------------------------------:|--------------------------------------|
|lnk      |      |                                                                      |length == 1, numeric, between  1 and 2|
|         |     1|create hyperlink xref                                                 |                                      |
|         |     2|create hyperlink xref and remove get parameters                       |                                      |
|img      |      |                                                                      |length == 1, numeric, between  1 and 4|
|         |     1|supress images                                                        |                                      |
|         |     2|replace images with IMAGE                                             |                                      |
|         |     3|replace images with alt="" tag                                        |                                      |
|         |     4|replace images with alt="" tag and create a image xref                |                                      |
|tgs      |      |                                                                      |length == 1, numeric, between  1 and 2|
|         |     1|preserve metatags                                                     |                                      |
|style    |      |                                                                      |length =< 255, string                 |
|         |path  |stylsheet used for printing                                           |                                      |
|page     |      |                                                                      |length =< 255, string                 |
|         |path  |page to print (relative or absolute path, _without_ hostname and port)|                                      |

Configuration
-------------
The web2printer configuration and localization is done within a ini file. The name is config-LANGUAGE.ini.
The Language parameter is substituted with the constructor parameter language.

For $web2printer = new Web2Printer("de"); the ini filename becomes config-de.ini
For $web2printer = new Web2Printer("en"); the ini filename becomes config-en.ini

### Configuration parameters
```
; active link
; 0 - hyperlinks not active (clickable) in the resulting html file
; 1 - hyperlinks active (clickable) in the resulting html file
activeLinks = 0
; 0 disable footer
; 1 enable  footer
; 2 use custom footer
footer = 1
; page url header:
pageUrlHeader = "<b>This page URLs</b>:<br>"
; page url crossreference header:
pageXRefURLHeader = "<b>Links:</b>"
; page images crossreference header:
pageXRefImages = "<b>Images:</b>"
; custom footer text - if you not enjoy the standard footer
; this page was generated with ....
customFooter = "";
```