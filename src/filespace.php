<?php

# This file loads the filespace, based on the PHPrepend Framework

# (c) Martin Lucas-Smith
# Licence: This is Free software, released without warranty under the GPL; see http://www.gnu.org/copyleft/gpl.html
# Version 2.11 - 12/May/04


# Define a class generating a filespace
class filespace
{
	# Constructor
	function filespace ($settings)
	{
		# Ensure the pureContent framework is loaded and clean server globals
		require_once ('pureContent.php');
		
		# Load the general application support
		require_once ('application.php');
		
		# Load the form generator library
		require_once ('ultimateForm.php');
		
		# Load the directories support library
		require_once ('directories.php');
		
		# Load the navigation trail library
		require_once ('sitetech/prepended.html');
		
		# Add a welcome message on the front page
		if ($_SERVER['REQUEST_URI'] == '/') {echo '<p><strong>Welcome to the ' . ORGANISATION_TITLE . ' filespace.</strong> <em>Please bookmark this page!</em><br />Files/folders can be added using the links above.</p>';}
		
		# Take action based on the query string
		switch ($_SERVER['QUERY_STRING']) {
			
			# If the query string is set to 'add', load the upload form library and create a form
			case 'add':
				$this->uploadForm ($settings['logFile'], $settings['bannedDirectories'], $settings['temporaryLocation'], $settings['maximumUploadFiles'], $settings['emailSubject']);
				break;
				
			# If the query string is set to 'directory', load the directory creation form library and create a form
			case 'directory':
				$this->createDirectory ($settings['logFile'], $settings['bannedDirectories'], $settings['goToCreatedDirectoryAutomatically']);
				break;
				
			# If no action is specified, by default show the directory listing
			default:
				echo directories::listing ($settings['iconsDirectory'], $settings['iconsServerPath'], $settings['hiddenFiles'], $settings['caseSensitiveMatching'], $settings['trailingSlashVisible'], $settings['fileExtensionsVisible'], $settings['wildcardMatchesZeroCharacters']);
		}
		
		# Finish the page
		require_once ('sitetech/appended.html');
	}
	
	
	# Function to create an upload form
	function uploadForm ($logfile, $bannedDirectories, $temporaryLocation, $maximumUploadFiles, $emailSubject)
	{
		# Get the location
		$location = $this->getLocation ();
		
		# Check for banned directories
		foreach ($bannedDirectories as $bannedDirectory) {
			if (strtolower ($location) == strtolower ($bannedDirectory)) {
				$location = $temporaryLocation;
				$locationDisallowedMessage = ' (The site administrator has not allowed changes in the location you selected.)';
			}
		}
		
		# Make sure the directory exists
		if (!is_dir ($_SERVER['DOCUMENT_ROOT'] . $location)) {$location = $temporaryLocation;}
		
		# If the location is the temporary location (e.g. has been reset), give a message to that effect
		if ($location == $temporaryLocation) {$locationMessage = "Temporary (the webmaster will move the file and inform you)";} else {$locationMessage = $location;}
		
		# Create the form
		$form = new form (array (
			'displayDescriptions'	=> false,
			'displayRestrictions'	=> false,
			'showFormCompleteText'	=> false,
			'displayColons'			=> false,
			'submitButtonText'		=> 'Copy over file(s)',
		));
		$form->heading ('p', 'Use this short form to copy file(s) across.');
		$form->heading ('p', 'Location: <strong>' . (($location != $temporaryLocation) ? '<a href="' . $location . '" target="_blank" title="(Opens in a new window)">' . $location . '</a>' : 'Temporary area') . '</strong>' . (isSet ($locationDisallowedMessage) ? $locationDisallowedMessage : ''));
		$form->upload (array (
			'elementName'			=> 'file',
			'title'					=> 'File(s) to copy over from your computer:',
			'uploadDirectory'		=> $_SERVER['DOCUMENT_ROOT'] . $location,
			'subfields'				=> 4,
			'presentationFormat'	=> array ('processing' => 'rawcomponents'),
			'minimumRequired'		=> 1,
			'enableVersionControl'	=> true,
		));
		$form->input (array (
			'elementName'			=> 'name',
			'title'					=> 'Your name:',
			'required'				=> true,
		));
		$form->email (array (
			'elementName'			=> 'email',
			'title'					=> 'Your e-mail address:',
			'required'				=> true,
		));
		$form->checkboxes (array (
			'elementName'			=> 'informGroup',
			'valuesArray'			=> array ('Inform group'),
			'title'					=> 'Tick to have an e-mail sent to ' . FILESPACE_GROUP_DESCRIPTION_BRIEF . ' informing them of the new file(s)',
		));
		$form->textarea (array (
			'elementName'			=> 'notes',
			'title'					=> 'Explanatory notes (optional):',
			'required'				=> false,
		));
		
		# Obtain the data from a posted form
		if ($result = $form->processForm ()) {
			$files = $result['file'];
			$name = $result['name'];
			$email = $result['email'];
			$informGroup = $result['informGroup'];
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
						#!# This whole stage needs to be moved into the form stage, by getting these raw components
						$filesize = ($_FILES['form']['size']['file'][$index] * 0.001);
						$filename = $_FILES['form']['name']['file'][$index];
						$filetype = $_FILES['form']['type']['file'][$index];
						
						# Make a list of successes
						$successesHtml .= "\n\t<li><a href=\"" . str_replace (' ', '%20', (htmlentities ($location . $filename))) . '">' . htmlentities ($filename) . '</a><span class="comment"> (size: ' . $filesize . ' KB; type: ' . $filetype . ")</span></li>\n";
						$logString .= $_SERVER['SERVER_NAME'] . ',' . date ('d/M/Y G:i:s') . ',' . $_SERVER['REMOTE_ADDR'] . ",$name,$email,added," . $location . ',' . $filename . ',' . $filesize . "\n";
						$emailMessage .= "\n\nhttp://" . $_SERVER['SERVER_NAME'] . str_replace (' ', '%20', ($location . $filename)) . "\n  (size: " . $filesize . ' KB)';
					}
				}
			}
			
			# Flag up any failures
			#!# Not currently being dealt with
			if ($failuresHtml != '') {echo $failuresHtml = '<p class="warning">For some reason, the ' . substr ($failuresHtml, 0, -4) . 'file' . ((count ($results) > 1) ? 's' : '') . ' did not copy over.</p>';}
			
			# Flag up the successes
			if ($successesHtml != '') {
				
				# Build up a success confirmation message and display it
				$html = "\n<p>Many thanks, $name. <strong>The following was successfully copied over</strong>:</p>";
				$html .= "\n<ul>";
				$html .= $successesHtml;
				$html .= "\n</ul>";
				$html .= "\n<p>Location: <a href=\"" . str_replace (' ', '%20', (htmlentities ($location))) . '">http://' . $_SERVER['SERVER_NAME'] . htmlentities ($location) . '</a></p>';
				echo $html;
				
				# Log the change
				application::writeDataToFile ($logString, $logfile);
				
				# Build up an e-mail message
				$emailMessage = "\n\nThe following was uploaded to the filespace by $name:\n" . $emailMessage;
				if ($location != '') {$emailMessage .= "\n\n\nLocation:\nhttp://" . $_SERVER['SERVER_NAME'] . str_replace (' ', '%20', ($location . $filename));}
				if ($notes != '') {$emailMessage .= "\n\n\nExplanatory notes:\n\n$notes";}
				
				# Start the e-mail headers
				$emailHeaders = 'From: ' . FILESPACE_WEBMASTER_DESCRIPTION . ' <' . FILESPACE_WEBMASTER_EMAIL . ">\n";
				$emailHeaders .= "Reply-To: $name <$email>\n";
				
				# Determine the e-mail recipient - only send to the group address if informGroup is requested AND the location is not the temporary location, but ensure the filespace administrator is informed either way
				if ($informGroup) {
					$emailRecipient = FILESPACE_GROUP_DESCRIPTION . ' <' . FILESPACE_GROUP_EMAIL . '>';
					$emailHeaders .= 'Cc: ' . FILESPACE_OWNER_DESCRIPTION . ' <' . FILESPACE_OWNER_EMAIL . '>' . "\n";
				} else {
					$emailRecipient = FILESPACE_OWNER_DESCRIPTION . ' <' . FILESPACE_OWNER_EMAIL . '>';
				}
				
				# If the temporary location is specified, note this in the e-mail to the administrator, specifying whether to reply to the group or the individual
				if ($location == '') {$emailMessage .= "\n\n\n**Note to the administrator: **\nPlease move the file and inform " . ($informGroup ? FILESPACE_GROUP_DESCRIPTION_BRIEF . 'and ' : '') . "$email where it is.";}
				
				# Send the e-mail
				if (!mail ($emailRecipient, $emailSubject, wordwrap ($emailMessage), $emailHeaders)) {
					echo '<p><strong>Although the file transfer was OK, there was some kind of problem with sending out a confirmation e-mail.</strong> Please contact the webmaster to inform them of the addition, at ' . FILESPACE_WEBMASTER_EMAIL . '.</p>';
				} else {
					# State that the e-mail has been sent and that it has (or hasn't) been logged
					echo '<p>An e-mail confirming this has been sent to ' . ($informGroup ? FILESPACE_GROUP_DESCRIPTION_BRIEF : FILESPACE_OWNER_DESCRIPTION_BRIEF) . ', and the change has been logged.</p>';
					if ($location == '') {echo '<p>The webmaster will move the upload to the correct place and send an e-mail accordingly.</p>';}
				}
			}
		}
	}
	
	
	# Function to create a new directory
	function createDirectory ($logfile, $bannedDirectories = array (), $goToCreatedDirectoryAutomatically = false)
	{
		# Get the location
		$location = $this->getLocation ();
		
		# Make sure the directory exists
		if (!is_dir ($_SERVER['DOCUMENT_ROOT'] . $location)) {
			echo '<p class="warning">You appear somehow to have selected a non-existent directory. Please select another.</p>';
			$locationAcceptable = false;
		} else {
			
			# Check for banned directories
			$locationAcceptable = true;
			foreach ($bannedDirectories as $bannedDirectory) {
				if (strtolower ($location) == strtolower ($bannedDirectory)) {
					echo '<p class="warning">New directories cannot be created in the particular location you specified. Please select another.</p>';
					$locationAcceptable = false;
					break;
				}
			}
		}
		
		# If the location is acceptable, continue
		if ($locationAcceptable) {
			
			# Create the form
			$form = new form (array (
				'displayDescriptions'	=> false,
				'displayRestrictions'	=> false,
				'showFormCompleteText'	=> false,
				'displayColons'			=> false,
				'submitButtonText'		=> 'Create new directory',
			));
			$form->heading ('p', 'Use this short form to create a new folder.');
			$form->input (array (
				'elementName'			=> 'directoryName',
				'title'					=> "<strong>New directory name: <a href=\"$location\" target=\"_blank\" title=\"(Opens in a new window)\">$location</a></strong>",
				'required'				=> true,
				#!# Doesn't work....
				'regexp'				=> '[^\\/:<>?|*"\']+',
			));
			$form->input (array (
				'elementName'			=> 'name',
				'title'					=> 'Your name:',
				'required'				=> true,
			));
			$form->email (array (
				'elementName'			=> 'email',
				'title'					=> 'Your e-mail address:',
				'required'				=> true,
			));
			
			# Obtain the data from a posted form
			if ($result = $form->processForm ()) {
				$directoryName = $result['directoryName'];
				$name = $result['name'];
				$email = $result['email'];
				
				# Check that it doesn't currently exist
				#!# Not a very elegant solution... need to find better way to integrate this with the form
				if (file_exists ($_SERVER['DOCUMENT_ROOT'] . $location . $directoryName)) {
					echo 'There already exists a file/directory by that name. Please go back and try again if necessary.';
				} else {
					
					# Attempt to create the directory
					umask (0);
					if (!mkdir (($_SERVER['DOCUMENT_ROOT'] . $location . $directoryName), 0770)) {
						echo '<p>Apologies, but there was a problem creating the directory.</p>';
					} else {
						
						# Log the directory creation
						$logString = $_SERVER['SERVER_NAME'] . ',' . date ('d/M/Y G:i:s') . ',' . $_SERVER['REMOTE_ADDR'] . ",$name,$email,created directory," . $location . ',' . $directoryName . ",,\n";
						application::writeDataToFile ($logString, $logfile);
						
						# Take the user directly to the new directory if required
						if ($goToCreatedDirectoryAutomatically) {
							#!# Surely this won't work because of the prepended file...?
							header ('Location: http://' . $_SERVER['SERVER_NAME'] . $location . $directoryName);
						} else {
							
							# Otherwise provide a link to the new location
							echo '<p>The directory was successfully created - <a href="' . $location . $directoryName . '/">go there now</a>.</p>';
						}
					}
				}
			}
		}
	}
	
	
	# Function to get the location
	function getLocation ()
	{
		# Determine the previous page variable, in the order HTTP_REFERER then a POST request, and remove the domain name prefix from the previous page location
		#!# This whole section needs serious refactoring
		$location = (eregi (('^http://' . $_SERVER['SERVER_NAME']), $_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '');
		$location = ereg_replace (('http://' . $_SERVER['SERVER_NAME']) , '', urldecode ($location));
		
		# Replace double-slashes in the location path
		while (strstr ($location, '//')) {$location = str_replace ('//', '/', $location);}
		#if (substr ($location, -1) != '/') {$location .= '/';} // Don't use this as it adds / to a .html file, i.e. .html/ , even though that may not actually be an issue
		
		# Remove ?add or ?directory from the path end in case those pages are used as a referer
		#!# Ideally rewrite more generic parser to exclude the final part from ? where that is actually a query string
		$location = ereg_replace ('\?add$', '', $location);
		$location = ereg_replace ('\?directory$', '', $location);
		
		# Return the location
		return $location;
	}
}

?>