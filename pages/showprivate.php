<?php
//  AcmlmBoard XD - Private message display page
//  Access: user, specifically the sender or reciever.

$title = __("Private messages");

if(!$loguserid)
	Kill(__("You must be logged in to view your private messages."));

if(!isset($_GET['id']) && !isset($_POST['id']))
	Kill(__("No PM specified."));

$id = (int)(isset($_GET['id']) ? $_GET['id'] : $_POST['id']);
$pmid = $id;

$staffpms = '';
if (HasPermission('admin.viewstaffpms')) $staffpms = ' OR userto={2}';

if(isset($_GET['snooping']))
{
	if(HasPermission('admin.viewpms'))
	{
		$rPM = Query("select * from {pmsgs} left join {pmsgs_text} on pid = {pmsgs}.id where {pmsgs}.id = {0}", $id);
		
		// log who's being Xkeeper
		Query("INSERT INTO {spieslog} (userid,date,pmid) VALUES ({0},UNIX_TIMESTAMP(),{1})", $loguserid, $id);
	}
	else
		Kill(__("No snooping for you."));
}
else
	$rPM = Query("select * from {pmsgs} left join {pmsgs_text} on pid = {pmsgs}.id where (userto = {1} or userfrom = {1}{$staffpms}) and {pmsgs}.id = {0}", $id, $loguserid, -1);

if(NumRows($rPM))
	$pm = Fetch($rPM);
else
	Kill(__("Unknown PM"));

if($pm['drafting'] && $pm['userfrom'] != $loguserid)
	Kill(__("Unknown PM")); //could say "PM is addresssed to you, but is being drafted", but what they hey?

$rUser = Query("select * from {users} where id = {0}", $pm['userfrom']);
if(NumRows($rUser))
	$user = Fetch($rUser);
else
	Kill(__("Unknown user."));

if(!isset($_GET['snooping']) && $pm['userto'] == $loguserid)
{
	$qPM = "update {pmsgs} set msgread=1 where id={0}";
	$rPM = Query($qPM, $pm['id']);
	$links = actionLinkTag(__("Send reply"), "sendprivate", "", "pid=".$pm['id']);
}
else if(!isset($_GET['snooping']) && $pm['drafting'])
{
	if($pm['userfrom'] != $loguserid)
		Kill(__("This PM is still being drafted."));
	else
		$draftEditor = true;
}
else if ($_GET['markread'])
{
	$qPM = "update {pmsgs} set msgread=1 where id={0}";
	$rPM = Query($qPM, $pm['id']);
	die(header('Location: '.actionLink('private')));
}
else if(isset($_GET['snooping']))
	Alert(__("You are snooping."));

$pmtitle = htmlspecialchars($pm['title']); //sender's custom title overwrites this below, so save it here
MakeCrumbs(array(actionLink("private") => __("Private messages"), '' => $pmtitle), $links);

$pm['num'] = "preview";
$pm['posts'] = $user['posts'];
$pm['id'] = "_";

foreach($user as $key => $value)
	$pm["u_".$key] = $value;

if($draftEditor)
{
	write(
"
	<script type=\"text/javascript\">
			window.addEventListener(\"load\",  hookUpControls, false);
	</script>
");


	$rUser = Query("select name from {users} where id={0}", $pm['userto']);
	if(!NumRows($rUser))
	{
		if($_POST['action'] == __("Send"))
			Kill(__("Unknown user."));
	}
	$user = Fetch($rUser);

	if($_POST['action'] == __("Preview"))
	{
		$pm['text'] = $_POST['text'];
		$pmtitle = $_POST['title'];
	}

	if($_POST['action'] == __("Discard Draft"))
	{
		Query("delete from {pmsgs} where id = {0}", $pmid);
		Query("delete from {pmsgs_text} where pid = {0}", $pmid);

		die(header("Location: ".actionLink("private")));
	}

	if(substr($pm['text'], 0, 17) == "<!-- ###MULTIREP:")
	{
		$to = substr($pm['text'], 17, strpos($pm['text'], "### -->") - 18);
		$pm['text'] = substr($pm['text'], strpos($pm['text'], "### -->") + 7);
	}

	if($_POST['action'] == __("Send") || $_POST['action'] == __("Update Draft"))
	{
		$recipIDs = array();
		if($_POST['to'])
		{
			$firstTo = -1;
			$recipients = explode(";", $_POST['to']);
			foreach($recipients as $to)
			{
				$to = trim(htmlentities($to));
				if($to == "")
					continue;
				$rUser = Query("select id from {users} where name={0} or displayname={0}", $to);
				if(NumRows($rUser))
				{
					$user = Fetch($rUser);
					$id = $user['id'];
					if($firstTo == -1)
						$firstTo = $id;
					/*if($id == $loguserid)
						$errors .= __("You can't send private messages to yourself.")."<br />";
					else*/ if(!in_array($id, $recipIDs))
						$recipIDs[] = $id;
				}
				//$maxRecips = array(-1 => 1, 3, 3, 3, 10, 100, 1);
				//$maxRecips = $maxRecips[$loguser['powerlevel']];
				//$maxRecips = ($loguser['powerlevel'] > 1) ? 5 : 1;
				$maxRecips = 5;
				if(count($recipIDs) > $maxRecips)
					$errors .= __("Too many recipients.");
				else
					$errors .= format(__("Unknown user \"{0}\""), $to)."<br />";
			}
			if($errors != "")
			{
				Alert($errors);
				$_POST['action'] = "";
			}
		}
		else
		{
			if($_POST['action'] == __("Send"))
				Alert("Enter a recipient and try again.", "Your PM has no recipient.");
			$_POST['action'] = "";
		}

		if($_POST['title'])
		{
			$_POST['title'] = $_POST['title'];

			if($_POST['text'])
			{
				if($_POST['action'] == __("Update Draft"))
				{
					$post = $pm['text'];
					$post = preg_replace("'/me '","[b]* ".$loguser['name']."[/b] ", $post); //to prevent identity confusion
						$post = "<!-- ###MULTIREP:".$_POST['to']." ### -->".$post;

					$rPMT = Query("update {pmsgs_text} set title = {0}, text = {1} where pid = {2}", $_POST['title'], $post, $pmid);
					$rPM = Query("update {pmsgs} set userto = {0} where id = {1}", $firstTo, $pmid);

                	die(header("Location: ".actionLink("private", "", "show=2")));
				}
				else
				{
					CheckPermission('user.sendpms');
					
					$post = $pm['text'];
					$post = preg_replace("'/me '","[b]* ".$loguser['name']."[/b] ", $post); //to prevent identity confusion

					$rPMT = Query("update {pmsgs_text} set title = {0}, text = {1} where pid = {2}", $_POST['title'], $post, $pmid);
					$rPM = Query("update {pmsgs} set drafting = 0 where id = {0}", $pmid);

					foreach($recipIDs as $recipient)
					{
						if($recipient == $firstTo)
							continue;

						$rPM = Query("insert into {pmsgs} (userto, userfrom, date, ip, msgread) values ({0}, {1}, {2}, {3}, 0)", $recipient, $loguserid, time(), $_SERVER['REMOTE_ADDR']);
						$pid = insertId();

						$rPMT = Query("insert into {pmsgs_text} (pid,title,text) values ({0}, {1}, {2})", $pid, $_POST['title'], $post);
					}

					die(header("Location: ".actionLink("private", "", "show=1")));
				}
			}
			else
				Alert(__("Enter a message and try again."), __("Your PM is empty."));
		}
		else
			Alert(__("Enter a title and try again."), __("Your PM is untitled."));
	}

	$prefill = $pm['text'];
	$trefill = $pmtitle;

	MakePost($pm, POST_PM);

	Write(
"
				<form action=\"".actionLink("showprivate")."\" method=\"post\">
					<table class=\"outline margin width100\">
						<tr class=\"header1\">
							<th colspan=\"2\">
								".__("Edit Draft")."
							</th>
						</tr>
						<tr class=\"cell0\">
							<td class=\"center\" style=\"width:15%; max-width:150px;\">
								".__("To")."
							</td>
							<td>
								<input type=\"text\" name=\"to\" style=\"width: 98%;\" maxlength=\"1024\" value=\"{2}\" />
							</td>
						</tr>
						<tr class=\"cell1\">
							<td class=\"center\">
								".__("Title")."
							</td>
							<td>
								<input type=\"text\" name=\"title\" style=\"width: 98%;\" maxlength=\"60\" value=\"{1}\" />
							</td>
						<tr class=\"cell0\">
							<td class=\"center\">
								".__("Message")."
							</td>
							<td>
								<textarea id=\"text\" name=\"text\" rows=\"16\" style=\"width: 98%;\">{0}</textarea>
							</td>
						</tr>
						<tr class=\"cell2\">
							<td></td>
							<td>
								<input type=\"submit\" name=\"action\" value=\"".__("Send")."\" />
								<input type=\"submit\" name=\"action\" value=\"".__("Preview")."\" />
								<input type=\"submit\" name=\"action\" value=\"".__("Update Draft")."\" />
								<input type=\"submit\" name=\"action\" value=\"".__("Discard Draft")."\" />
								<input type=\"hidden\" name=\"id\" value=\"{3}\" />
							</td>
						</tr>
					</table>
				</form>
",	$prefill, $trefill, $to, $pmid);

}
else
{
	MakePost($pm, POST_PM);
}

?>
