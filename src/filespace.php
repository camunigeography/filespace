<?php

# Class to create a helpdesk function
# Version 2.2.1

# Licence: GPL
# (c) Martin Lucas-Smith, University of Cambridge
# More info: http://download.geog.cam.ac.uk/projects/helpdesk/


# Define a class generating a filespace
class filespace
{
	# Constructor
	function filespace ($settings)
	{
		# Load required libraries
		require_once ('pureContent.php');
		require_once ('application.php');
		require_once ('ultimateForm.php');	// Unzipping support requires 1.2.1 or later
		require_once ('directories.php');
		
		# Assign the settings and run the main program if there are no errors
		if (!$this->setup ($settings)) {return false;}
		
		# Start the page
		echo $this->settings['header'];
		echo "\n\t\t" . '<div id="header">
			<ul class="functions">
				<li><a href="?add">+ Add <strong>new item</strong> here</a></li>
				<li><a href="?directory">&radic; Add new folder here</a></li>
				' . ($_SERVER['QUERY_STRING'] == 'date' ? '<li><a href="./">N Change to: list by name</a></li>' : '<li><a href="?date">D Change to: list by date</a></li>') . '
				' . (ereg ('^(add|directory)$', $_SERVER['QUERY_STRING']) ? '<li><a href="./">&laquo; Return to listing</a></li>' : '') . '
			</ul>
			<h1><a href="/">' . $this->settings['organisationTitle'] . ' filespace</a></h1>
		</div>';
		echo "\n\n\t\t" . '<p class="navigation">You are in: ' . directories::trail ()  . '</p>';
		
		# Determine the current location
		$this->location = str_replace ('?' . $_SERVER['QUERY_STRING'], '', urldecode ($_SERVER['REQUEST_URI']));
		
		# Add a welcome message on the front page
		if ($this->location == '/') {
			echo "\n\n\t\t" . '<p><strong>Welcome to the ' . $this->settings['organisationTitle'] . ' filespace.</strong> <em>Please bookmark this page!</em><br />Files/folders can be added using the links above.</p>';
			echo $this->settings['frontPageText'];
		}
		
		# Take action based on the query string
		switch ($_SERVER['QUERY_STRING']) {
			
			# If the query string is set to 'add', load the upload form library and create a form
			case 'add':
				$this->uploadForm ();
				break;
				
			# If the query string is set to 'directory', load the directory creation form library and create a form
			case 'directory':
				$this->createDirectory ($this->settings['bannedDirectories'], $this->settings['goToCreatedDirectoryAutomatically']);
				break;
				
			# If no action is specified, by default show the directory listing
			default:
				echo directories::listing ($this->settings['iconsDirectory'], $this->settings['iconsServerPath'], ($this->settings['photoModeOnly'] ? array_merge ($this->settings['hiddenFiles'], array ('*.jpg', '*.gif', '*.png')) : $this->settings['hiddenFiles']), $this->settings['caseSensitiveMatching'], $this->settings['trailingSlashVisible'], $this->settings['fileExtensionsVisible'], $this->settings['wildcardMatchesZeroCharacters'], $this->settings['showOnly'], ($_SERVER['QUERY_STRING'] == 'date' ? 'time' : 'name'));
		}
		
		# Show photo thumbnails if required
		if ($this->settings['photoDirectory']) {
			if (eregi ('^' . $this->settings['photoDirectory'], $_SERVER['REQUEST_URI'])) {
				require_once ('image.php');
				echo image::gallery (true, false, $size = 180);
			}
		}
		
		# Finish the page
		echo $this->settings['footer'];
	}
	
	
	# Assign the settings
	function setup ($settings)
	{
		# Start an error array
		$errors = array ();
		
		# Define the default template
		$header = '
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html lang="en">
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=ISO-8859-1" />
	<title>{organisationTitle} filespace</title>
	<style type="text/css" media="screen">
		/* Layout */
		body {text-align: center; min-width: 760px;}
		#container {width: 760px; margin-left: auto; margin-right: auto; text-align: left;}
		#header {height: 5em;}
		#footer {clear: both; border-top: 1px solid #ddd; margin-top: 30px;}
		/* Body */
		body, input, textarea {font-family: verdana, arial, helvetica, sans-serif;}
		/* Font sizes - uses technique at http://www.thenoodleincident.com/tutorials/typography/ */
		body {font-size: 69%;}
		input, textarea {font-size: 1em;}
		/* Links */
		a {text-decoration: none;}
		a:hover {text-decoration: underline;}
		/* Header */
		h1 {width: 20em; font-size: 1.8em; text-align: left; padding-right: 100px; padding-bottom: 5px; border-bottom: 1px solid #ddd;}
		h1, h1 a, h1 a:visited {color: #555; text-decoration: none;}
		ul.functions {width: 14.5em; float: right; margin: 0; padding: 0;}
		ul.functions li {list-style-type: none; margin-bottom: 0.3em;}
		ul.functions a {display: block; width: 100%; padding: 2px 4px; border: 1px solid #ccc;}
		ul.functions a:hover {text-decoration: none; background-color: #f7f7f7;}
		/* Navigation bar */
		p.navigation {clear: both; border-bottom: 1px solid #ddd; padding-bottom: 4px; margin-bottom: 30px;}
		p.navigation a {padding: 3px;}
		/* Listing */
		ul.filelist {list-style: none; margin: 0; padding: 0;}
		ul.filelist li a {font-weight: bold;}
		ul.filelist li {color: gray;}
		ul.filelistnotes {margin-top: 35px;}
		img {border: 0;}
		/* Forms */
		td {padding: 10px 2px 0;}
		td.title {text-align: right; vertical-align: top;}
		.error {color: red;}
		.restriction, .description {color: #999; font-style: italic;}
		input, select, textarea, option, td.data label {color: #603;}
		/* Message box */
		div.messagebox {float: right; width: 25%; border-left: 1px dashed #ccc; margin: 0 0 30px 15px; padding: 10px;}
		div.messagebox ul {margin-left: 15px;}
		/* Gallery */
		div.gallery div.image {display: block; float: left; text-align: center;}
		div.gallery div.image img {border: 1px solid #333; margin: 30px 10px 5px 0;}
		div.gallery div.image p {margin-top: 0; margin-bottom: 0;}
	</style>
	<script language="javascript" type="text/javascript">
		function setFocus() {
			if (document.forms.length > 0) {
				var field = document.forms[0];
				for (i = 0; i < field.length; i++) {
					if ((field.elements[i].type == "text") || (field.elements[i].type == "textarea") || (field.elements[i].type.toString().charAt(0) == "s")) {
						document.forms[0].elements[i].focus();
						break;
					}
				}
			}
		}
	</script>
</head>
<body onload="setFocus()">

<div id="container">
	<div id="content">
	';
	
	$footer = '
		<div id="footer">
			<p>For any technical problems found, please <a href="{administratorContactPage}" target="_blank" title="(Opens in a new window)">contact the webmaster</a>.</p>
		</div>
	</div>
</body>
</html>
		';
		
		# Assign the defaults
		$defaults = array (
			'header' => $header,
			'footer' => $footer,
			'iconsDirectory' =>	'/images/fileicons/',	// Icons directory
			'iconsServerPath' =>	$_SERVER['DOCUMENT_ROOT'] . '/images/fileicons/',	// Icons path on the server
			'trailingSlashVisible' =>	false,	// Whether folders should have a trailing slash visible
			'fileExtensionsVisible' =>	true,	// Whether file extensions should be visible
			'wildcardMatchesZeroCharacters' =>	true,	// Whether a wildcard match should match zero characters (0) or not (1 or more)
			'caseSensitiveMatching' =>	false,	// Whether file matching should ignore case-sensitivity
			'goToCreatedDirectoryAutomatically' =>	false,	// Whether creation of a new directory should result in a link to it or take the user directly there; requires output_buffering switched on in php.ini (or equivalent)
			'uploadWidgets' =>	4,	// Number of upload boxes to appear on the page
			'enableVersionControl' =>	true,	// Whether to enable version control for replacing existing files
			'emailAddressRequired' =>	true,	// Whether an e-mail address is required
			'temporaryLocation' =>	'/temporary/',	// Temporary location for unspecified file saving, make sure this exists and is writable by the webserver 'user'!
			'bannedDirectories' =>	array ('/temporary/'),	// Case insensitive directories where users cannot make changes, does NOT include subdirectories
			'emailSubject' =>	'Addition to the filespace',	// E-mail subject line
			'logFile' =>	'./filespacelog.csv',	// The CSV log file (actual disk location) where changes are logged, ensure this exists and is writable by the webserver 'user'
			'hiddenFiles' =>	array ('.ht*', '.title.txt', '/favicon.ico', '/robots.txt', '/temporary/', '/changelog.csv',),	// Hidden files, starting with / indicates an absolute path and * at the start/end (but not middle) is a wildcard match, trailing slashes optional
			'organisationTitle' =>	NULL,	// Title of the organsation
			'administratorContactPage' =>	NULL,	// The site's administrator contact page
			'administratorEmail' =>	NULL,	// Webmaster's e-mail
			'administratorDescription' =>	'Filespace monitor',	// Administrator's description
			'ownerEmail' =>	NULL,	// Owner's e-mail
			'ownerDescription' =>	'Filespace owner',	// Owner's description
			'ownerDescriptionBrief' =>	'the filespace owner',	// Owner's description in brief
			'groupEmail' =>	NULL,	// Group's e-mail
			'groupDescription' =>	'Committee',	// Group's description
			'groupDescriptionBrief' =>	'the committee',	// Group's description in brief
			'prependedFile' =>	NULL,	// Start of house style
			'appendedFile' =>	NULL,	// End of house style
			'frontPageText' =>	'',	// Optional text on the front page
			'developmentEnvironment' =>	false,	// Whether to run in development mode
			'photoDirectory' =>	false,	// Directory (and subdirectories underneath) where thumbnails should be added if any images are present, or false to disable
			'photoModeOnly' => false,	// In photo mode, only the thumbnails are shown rather than any file listing
			'showOnly' => array (),	// Only these directories should be shown
			'unzip' => true,	// Whether to unzip zip files on arrival
			'emailSubjectLine' => true,	// Whether to allow the e-mail subject line to be set
		);
		
		# Apply the supplied argument or, if none, the default
		foreach ($defaults as $key => $default) {
			
			# Throw an error if a value is null
			if ((is_null ($default)) && (!isSet ($settings[$key]))) {
				$errors["settings-{$key}"] = "The setting $key is a required setting without an internal default but has not been assigned.";
			} else {
				
				# Assign the setting
				$this->settings[$key] = (isSet ($settings[$key]) ? $settings[$key] : $default);
			}
		}
		
		# Apply string replacement to the header and footer
		$this->settings['header'] = str_replace ('{organisationTitle}', $this->settings['organisationTitle'], $this->settings['header']);
		$this->settings['footer'] = str_replace ('{administratorContactPage}', $this->settings['administratorContactPage'], $this->settings['footer']);
		
		# Show any setup errors
		if ($errors) {
			$html  = "\n" . '<p>This program cannot currently run because of the following problem' . ((count ($errors) > 1) ? 's' : '') . ':</p>';
			$html .= application::htmlUl ($errors);
			$html .= "\n" . "<p>These should be corrected by the server's administrator.</p>";
			echo $html;
			return false;
		}
		
		# Return success
		return true;
	}
	
	
	# Function to create an upload form
	function uploadForm ()
	{
		# Get the location
		$location = $this->location;
		
		# Check for banned directories
		if (application::iin_array ($location, $this->settings['bannedDirectories'])) {
			$location = $this->settings['temporaryLocation'];
			$locationDisallowedMessage = ' (The site administrator has not allowed changes in the location you selected.)';
		}
		
		# Make sure the directory exists
		if (!is_dir ($_SERVER['DOCUMENT_ROOT'] . $location)) {$location = $this->settings['temporaryLocation'];}
		
		# If the location is the temporary location (e.g. has been reset), give a message to that effect
		if ($location == $this->settings['temporaryLocation']) {$locationMessage = "Temporary (the webmaster will move the file and inform you)";} else {$locationMessage = $location;}
		
		# Check if unzipping support available
		$this->settings['unzip'] = ($this->settings['unzip'] && extension_loaded ('zip'));
		
		# Create the form
		$form = new form (array (
			'formCompleteText'	=> false,
			'submitButtonText'		=> 'Copy over file(s)',
			'developmentEnvironment' => $this->settings['developmentEnvironment'],
		));
		
		$form->heading ('p', 'Use this short form to copy file(s) across.');
		$form->heading ('p', 'Location: <strong>' . (($location != $this->settings['temporaryLocation']) ? '<a href="' . $location . '" target="_blank" title="(Opens in a new window)">' . $location . '</a>' : 'Temporary area') . '</strong>' . (isSet ($locationDisallowedMessage) ? $locationDisallowedMessage : ''));
		$form->upload (array (
			'name'			=> 'file',
			'title'					=> 'File(s) to copy over from your computer<br />(max. ' . ini_get ('upload_max_filesize') . 'B per submission</strong> of any number of files)',
			'directory'		=> $_SERVER['DOCUMENT_ROOT'] . $location,
			'subfields'				=> $this->settings['uploadWidgets'],
			'output'	=> array ('processing' => 'rawcomponents'),
			'required'		=> 1,
			'enableVersionControl'	=> $this->settings['enableVersionControl'],
			'unzip' => $this->settings['unzip'],
		));
		$form->input (array (
			'name'			=> 'name',
			'title'					=> 'Your name',
			'required'				=> true,
		));
		$form->email (array (
			'name'			=> 'email',
			'title'					=> 'Your e-mail address',
			'required'				=> $this->settings['emailAddressRequired'],
		));
		$form->checkboxes (array (
			'name'			=> 'informGroup',
			'values'			=> array ('Inform group'),
			'title'					=> 'Tick to have an e-mail sent to ' . $this->settings['groupDescription'] . ' informing them of the new file(s)',
		));
		if ($this->settings['emailSubjectLine']) {
			$form->input (array (
				'name'			=> 'subject',
				'title'					=> 'Subject line (optional; used for e-mail notifications)',
				'required'				=> false,
				'maxlength'		=> 80,
			));
		}
		$form->textarea (array (
			'name'			=> 'notes',
			'title'					=> 'Explanatory notes (optional)',
			'required'				=> false,
		));
		
		# Obtain the data from a posted form
		if (!$result = $form->processForm ()) {return false;}
		$files = $result['file'];
		$name = $result['name'];
		$email = $result['email'];
		$informGroup = $result['informGroup']['Inform group'];
		$notes = $result['notes'];
		
		# Start variables to hold HTML and e-mail messages and a logfile entry
		$failuresHtml = '';
		$successesHtml = '';
		$emailMessage = '';
		$logString = '';
		
		# Loop through each uploadable file
		foreach ($_FILES['form']['name'] as $files) {
			foreach ($files as $index => $file) {
				if (!empty ($file)) {
					
					# Obtain the size, name and type
					#!# This whole stage needs to be moved into the form stage, by getting these raw components from $result directly
					$filesize = ($_FILES['form']['size']['file'][$index] * 0.001);
					$filename = $_FILES['form']['name']['file'][$index];
					$filenameLink = $location . ($this->settings['unzip'] && strtolower (substr ($filename, -4)) == '.zip' ? '' : $filename);
					$filename = ($this->settings['unzip'] && strtolower (substr ($filename, -4)) == '.zip' ? " [{$filename} unzipped]" : $filename);
					$filetype = $_FILES['form']['type']['file'][$index];
					
					# Make a list of successes
					#!# Needs to take account of unzipping
					$successesHtml .= "\n\t<li><a href=\"" . str_replace (' ', '%20', (htmlentities ($filenameLink))) . '">' . htmlentities ($filename) . '</a><span class="comment"> (size: ' . $filesize . ' KB; type: ' . $filetype . ")</span></li>\n";
					$logString .= $_SERVER['SERVER_NAME'] . ',' . date ('d/M/Y G:i:s') . ',' . $_SERVER['REMOTE_ADDR'] . ",$name,$email,added," . $location . ',' . $_SERVER['DOCUMENT_ROOT'] . '/' . $filename . ',' . $filesize . "\n";
					$emailMessage .= "\n\nhttp://" . $_SERVER['SERVER_NAME'] . str_replace (' ', '%20', ($filenameLink)) . ($this->settings['unzip'] && (substr ($_FILES['form']['name']['file'][$index], -4)) == '.zip' ? "\n{$filename}" : '') . "\n  (size: " . $filesize . ' KB)';
				}
			}
		}
		
		# Flag up any failures
		#!# Not currently being dealt with
		if ($failuresHtml) {
			echo $failuresHtml = '<p class="warning">For some reason, the ' . substr ($failuresHtml, 0, -4) . 'file' . ((count ($results) > 1) ? 's' : '') . ' did not copy over.</p>';
		}
		
		# Flag up the successes
		if (!$successesHtml) {return false;}
		
		# Build up a success confirmation message and display it
		$html  = "\n<p>Many thanks, $name. <strong>The following was successfully copied over</strong>:</p>";
		$html .= "\n<ul>\n{$successesHtml}\n</ul>";
		$html .= "\n<p>Location: <a href=\"" . str_replace (' ', '%20', (htmlentities ($location))) . '">http://' . $_SERVER['SERVER_NAME'] . htmlentities ($location) . '</a></p>';
		echo $html;
		
		# Log the change
		application::writeDataToFile ($logString, $this->settings['logFile']);
		
		# Build up an e-mail message
		$message  = "\n\nThe following was uploaded to the filespace by $name:";
		if ($notes) {$message .= "\n\n\nExplanatory notes:\n\n$notes";}
		$message  .= "\n" . $emailMessage;
		if ($location) {$message .= "\n\n\nLocation:\nhttp://" . $_SERVER['SERVER_NAME'] . str_replace (' ', '%20', $location);}
		
		# Start the e-mail headers
		$emailHeaders = 'From: "' . $this->settings['administratorDescription'] . '" <' . $this->settings['administratorEmail'] . ">\n";
		if ($email) {$emailHeaders .= "Reply-To: \"$name\" <$email>\n";}
		
		# Determine the e-mail recipient - only send to the group address if informGroup is requested AND the location is not the temporary location, but ensure the filespace administrator is informed either way
		if ($informGroup) {
			$emailRecipient = '"' . $this->settings['groupDescription'] . '" <' . $this->settings['groupEmail'] . '>';
			$emailHeaders .= 'Cc: "' . $this->settings['ownerDescription'] . '" <' . $this->settings['ownerEmail'] . '>' . "\n";
		} else {
			$emailRecipient = '"' . $this->settings['ownerDescription'] . '" <' . $this->settings['ownerEmail'] . '>';
		}
		
		# If the temporary location is specified, note this in the e-mail to the administrator, specifying whether to reply to the group or the individual
		if ($location == '') {$message .= "\n\n\n**Note to the administrator: **\nPlease move the file and inform " . ($informGroup ? $this->settings['groupDescriptionBrief'] . 'and ' : '') . "$email where it is.";}
		
		# Send the e-mail
		if (!mail ($emailRecipient, ($this->settings['emailSubjectLine'] && $result['subject'] ? $result['subject'] : $this->settings['emailSubject']), wordwrap ($message), $emailHeaders)) {
			echo '<p><strong>Although the file transfer was OK, there was some kind of problem with sending out a confirmation e-mail.</strong> Please contact the webmaster to inform them of the addition, at ' . $this->settings['administratorEmail'] . '.</p>';
			return false;
		}
		
		# State that the e-mail has been sent and that it has (or hasn't) been logged
		echo '<p>An e-mail confirming this has been sent to ' . ($informGroup ? $this->settings['groupDescriptionBrief'] : $this->settings['ownerDescriptionBrief']) . ', and the change has been logged.</p>';
		if ($location == '') {echo '<p>The webmaster will move the upload to the correct place and send an e-mail accordingly.</p>';}
	}
	
	
	# Function to create a new directory
	function createDirectory ($bannedDirectories = array (), $goToCreatedDirectoryAutomatically = false)
	{
		# Get the location
		$location = $this->location;
		
		# Make sure the directory exists
		if (!is_dir ($_SERVER['DOCUMENT_ROOT'] . $location)) {
			echo '<p class="warning">You appear somehow to have selected a non-existent directory. Please select another.</p>';
			return;
		}
		
		# Check for banned directories
		if (application::iin_array ($location, $bannedDirectories)) {
			echo '<p class="warning">New folders cannot be created in the particular location you specified. Please select another.</p>';
			return false;
		}
		
		# Construct a regexp of disallowed filenames (odd characters plus existing files)
		#!# On Windows this won't catch the same name in different case
		$current = directories::listFiles ($location);
		$disallow = '(([\\/:<>?|*"\']+)' . ($current ? '|(^(' . implode ('|', array_keys ($current)) . ')$)' : '') . ')';
		
		# Create the form
		$form = new form (array (
			'formCompleteText'	=> false,
			'submitButtonText'		=> 'Create new directory',
			'developmentEnvironment' => $this->settings['developmentEnvironment'],
		));
		$form->heading ('p', 'Use this short form to create a new folder.');
		$form->input (array (
			'name'			=> 'directoryName',
			'title'					=> "<strong>New directory name <a href=\"$location\" target=\"_blank\" title=\"(Opens in a new window)\">$location</a></strong>",
			'description'			=> 'Special characters and existing folder names disallowed',
			'required'				=> true,
			'disallow'				=> $disallow,
		));
		$form->input (array (
			'name'			=> 'name',
			'title'					=> 'Your name',
			'required'				=> true,
		));
		$form->email (array (
			'name'			=> 'email',
			'title'					=> 'Your e-mail address',
			'required'				=> $this->settings['emailAddressRequired'],
		));
		
		# Obtain the data from a posted form
		if (!$result = $form->processForm ()) {return false;}
		
		# Attempt to create the directory
		umask (0);
		if (!mkdir (($_SERVER['DOCUMENT_ROOT'] . $location . $result['directoryName']), 0770)) {
			echo '<p class="warning">Apologies, but there was a problem creating the directory.</p>';
			return false;
		}
		
		# Log the directory creation
		$logString = $_SERVER['SERVER_NAME'] . ',' . date ('d/M/Y G:i:s') . ',' . $_SERVER['REMOTE_ADDR'] . ",{$result['name']},{$result['email']},created directory," . $location . ',' . $result['directoryName'] . ",,\n";
		application::writeDataToFile ($logString, $this->settings['logFile']);
		
		# Otherwise provide a link to the new location
		echo '<p>The directory was successfully created - <a href="' . $location . $result['directoryName'] . '/">go there now</a>.</p>';
		
		# Take the user directly to the new directory if required and output_buffering is on
		if ($goToCreatedDirectoryAutomatically && (ini_get ('output_buffering'))) {
			header ('Location: http://' . $_SERVER['SERVER_NAME'] . $location . $result['directoryName']);
		}
	}
}

?>