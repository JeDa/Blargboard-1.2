<?php

if ($isHidden) return;

$c1 = ircColor(Settings::pluginGet("color1"));
$c2 = ircColor(Settings::pluginGet("color2"));

$thename = $loguser["name"];
if($loguser["displayname"])
	$thename = $loguser["displayname"];
	
$fpage = ircForumPrefix($forum);
$link = getServerURLNoSlash().actionLink("post", $pid);

ircReport("\003".$c2."New reply by\003$c1 "
	.ircUserColor($thename, $loguser['sex'], 0)
	."\003$c2: \003$c1"
	.$thread["title"]
	."\003$c2 (".$fpage.$forum["title"].")"
	." -- "
	.$link
	);
	
?>
