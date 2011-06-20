<?php

// Forbid prefetching, we don't need greedy browsers killing us
if (isset($_SERVER['HTTP_X_MOZ']) && $_SERVER['HTTP_X_MOZ'] == 'prefetch') {
    header('HTTP/1.1 403 Prefetch Forbidden');
    exit();
}

ob_start();

require_once "init.php";

date_default_timezone_set(isset($global_timezone) ? $global_timezone : "UTC");

$serverName = isset($_SERVER['SERVER_NAME']) ? $_SERVER['SERVER_NAME'] : "killwhore.com";
$subDomain = str_replace("killwhore.com", "", $serverName);
if (strlen($subDomain) > 1) $subDomain = substr($subDomain, 0, strlen($subDomain) - 1);

if (strlen($subDomain) > 1) $baseUrl = str_replace("http://", "http://$subDomain.", $baseUrl);
$timer = new Timer();

$types = array("Meta", "Header", "Menu", "LeftPane", "MidPane", "RightPane", "Footer");
$context = array();

$indexes = array();

$ajaxCall = false;

$DEBUG = in_array("DEBUG", $p);

$pages = array(
    "default" => new SearchPage(),
    "related" => new RelatedPage(),
    "killmail" => new KillmailPage(),
    "post" => new PostPage(),
    "faq" => new FaqPage(),
);
$page = sizeof($p) > 0 && isset($pages[$p[0]]) ? $pages[$p[0]] : $pages["default"];
$page->initialize($p, $context);
$page->callControllers();

$menuOptions = array();
foreach ($pages as $pageName => $pageClass) {
    $menuOptions = array_merge($menuOptions, $pageClass->getMenuOptions());
}

// Output each view for each plugin in the requested order of each plugin
foreach ($types as $type) {
    global $pluginBaseUrl, $baseUrl;

    if (!$ajaxCall) preProcessPane($type);
    $functionName = "view{$type}";
    $page->$functionName(null);
    if (!$ajaxCall) postProcessPane($type);
}

$pageResult = ob_get_flush();
//if ($fullURL != null) memcached::set($fullURL, $pageResult, 60);

function preProcessPane($type)
{
    global $baseUrl, $subDomain, $p, $menuOptions;

    switch ($type) {
        case "Meta":
            echo '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">', "\n";
            echo '<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en">', "\n";
            echo "<head>\n";
            echo "\t<meta http-equiv=\"content-type\" content=\"text/html; charset=utf-8\" />\n";
            if (strlen($subDomain) == 0 && (sizeof($p) == 0 || (sizeof($p) > 0 && ($p[0] == "killmail" || $p[0] == "date"))))
                echo "\t<meta name=\"robots\" content=\"index,follow\"/>\n";
            else
                echo "\t<meta name=\"robots\" content=\"noindex,nofollow\"/>\n";
            echo "\t<link rel=\"stylesheet\" href=\"$baseUrl/css/killwhore.css\" type=\"text/css\" />\n";
            //echo "\t<script type='text/javascript'>!window.jQuery && document.write('<script src=\"$baseUrl/js/jquery-1.5.1.min.js\"><\/script>');</script>\n";
            break;
        case "Header":
            echo "<div id='content'><div id='contentExcludingFooter'><div id='headerPane'>\n";
            echo "<a href='$baseUrl/'><img src='/images/killwhore.png' alt='Primary. Banner'/></a>";
            break;
        case "Menu":
            echo "<div id='menu'><a href='$baseUrl'>Home</a>";
            foreach ($menuOptions as $name => $url) {
                echo "<a href='/$url'>$name</a>";
            }
            break;
        case "LeftPane":
            echo "<table id='contentTable'><tr><td id='leftPane'>\n";
            break;
        case "MidPane":
            echo "<td id='midPane'>\n";
            break;
        case "RightPane":
            echo "<td id='rightPane'>\n";
            break;
        case "Footer":
            echo "<br/><br/><div id='footer'><span class='footerContent'>\n";
            break;
    }
}

function postProcessPane($type)
{
    global $timer, $context, $p;

    switch ($type) {
        case "Meta":
            echo "<script type=\"text/javascript\">

  var _gaq = _gaq || [];
  _gaq.push(['_setAccount', 'UA-23312705-1']);
  _gaq.push(['_setDomainName', '.killwhore.com']);
  _gaq.push(['_trackPageview']);

  (function() {
    var ga = document.createElement('script'); ga.type = 'text/javascript'; ga.async = true;
    ga.src = ('https:' == document.location.protocol ? 'https://ssl' : 'http://www') + '.google-analytics.com/ga.js';
    var s = document.getElementsByTagName('script')[0]; s.parentNode.insertBefore(ga, s);
  })();

</script>\n";
            $title = isset($context['pageTitle']) ? " - " . $context['pageTitle'] : "";
            if (sizeof($p) > 0) $title .= " - " . implode(" ", $p);
            echo "\t<title>KillWhore$title</title>\n";
            echo "</head>\n";
            echo "<body>\n";
            break;
        case "Header":
            echo "</div>\n";
            break;
        case "Menu":
            echo "</div>\n";
            break;
        case "LeftPane":
            echo "</td>\n";
            break;
        case "MidPane":
            echo "<hr id='footerPadding'/></td>\n";
            break;
        case "RightPane":
            echo "</td></tr></table>\n";
            break;
        case "Footer":
            $queryCount = Db::getQueryCount();
            global $startTime;
            $elapsed = (int)$timer->stop();
            echo " KillWhore | zKillBoard v0.1b | ($elapsed", "ms/$queryCount", "q)\n"; // End of Content Div
            echo '<a href="#" onclick="return false" title="
EVE Online and the EVE logo are the registered trademarks of CCP hf. All rights are reserved worldwide.
All other trademarks are the property of their respective owners.
EVE Online, the EVE logo, EVE and all associated logos and designs are the intellectual property of CCP hf.
All artwork, screenshots, characters, vehicles, storylines, world facts or other recognizable features of the intellectual property relating to these trademarks are likewise the intellectual property of CCP hf.
CCP hf. has granted permission to KillWhore.com to use EVE Online and all associated logos and designs for promotional and information purposes on its website but does not endorse, and is not in any way affiliated with, KillWhore.com.
CCP is in no way responsible for the content on or functioning of this website, nor can it be liable for any damage arising from the use of this website.
">CCP Copyright Notice</a>';
            echo "</div></span></div></div></body></html>\n";
            break;
    }
}

