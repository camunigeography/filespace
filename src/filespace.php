<?php

# This file loads the filespace, based on the PHPrepend Framework

# (c) Martin Lucas-Smith
# Licence: This is Free software, released without warranty under the GPL; see http://www.gnu.org/copyleft/gpl.html
# Version 2.12 - 8/Oct/04


# Define a class generating a filespace
class filespace
{
	# Constructor
	function filespace ($settings)
	{
		# Load required libraries
		require_once ('pureContent.php');
		require_once ('application.php');
		require_once ('ultimateForm.php');
		require_once ('directories.php');
		
		# Assign the settings and run the main program if there are no errors
		if ($errors = $this->assignSettings ($settings)) {return;}
		
		# Load the navigation trail library
		require_once ($this->settings['prependedFile']);
		
		# Add a welcome message on the front page
		if ($_SERVER['REQUEST_URI'] == '/') {
			echo '<p><strong>Welcome to the ' . $this->settings['organisationTitle'] . ' filespace.</strong> <em>Please bookmark this page!</em><br />Files/folders can be added using the links above.</p>';
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
				echo directories::listing ($this->settings['iconsDirectory'], $this->settings['iconsServerPath'], $this->settings['hiddenFiles'], $this->settings['caseSensitiveMatching'], $this->settings['trailingSlashVisible'], $this->settings['fileExtensionsVisible'], $this->settings['wildcardMatchesZeroCharacters']);
		}
		
		# Show photo thumbnails if required
		if ($this->settings['photoDirectory']) {
			if (eregi ('^' . $this->settings['photoDirectory'], $_SERVER['REQUEST_URI'])) {
				require_once ('image.php');
				image::gallery (substr (urldecode ($_SERVER['REQUEST_URI']), 1), '/images/generator', $width = 180, false);
			}
		}
		
		# Finish the page
		require_once ($this->settings['appendedFile']);
	}
	
	
	# Assign the settings
	function assignSettings ($settings)
	{
		# Start an error array
		$errors = array ();
		
		# Assign the defaults
		$defaults = array (
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
			'developmentEnvironment' =>	false,	// Whether to run in development mode
			'photoDirectory' =>	false,	// Directory (and subdirectories underneath) where thumbnails should be added if any images are present, or false to disable
		);
		
		# Apply the supplied argument or, if none, the default
		foreach ($defaults as $key => $default) {
			
			# Throw an error if a value is null
			if ((is_null ($default)) && (!isSet ($settings[$key]))) {
				$errors["settings-{$key}"] = "The setting $key is a required setting without an internal default but has not been assigned.";
			} else {
				
				# Assign the setting
				$settings[$key] = (isSet ($settings[$key]) ? $settings[$key] : $default);
			}
		}
		
		# Assign the settings
		$this->settings = $settings;
		
		# Show any setup errors
		if ($errors) {
			$html  = "\n" . '<p>This program cannot currently run because of the following problem' . ((count ($errors) > 1) ? 's' : '') . ':</p>';
			$html .= application::htmlUl ($errors);
			$html .= "\n" . "<p>These should be corrected by the server's administrator.</p>";
			echo $html;
		}
		
		# Return the settings
		return ($errors);
	}
	
	
	# Function to create an upload form
	function uploadForm ()
	{
		# Get the location
		$location = $this->getLocation ();
		
		# Check for banned directories
		foreach ($this->settings['bannedDirectories'] as $bannedDirectory) {
			if (strtolower ($location) == strtolower ($bannedDirectory)) {
				$location = $this->settings['temporaryLocation'];
				$locationDisallowedMessage = ' (The site administrator has not allowed changes in the location you selected.)';
			}
		}
		
		# Make sure the directory exists
		if (!is_dir ($_SERVER['DOCUMENT_ROOT'] . $location)) {$location = $this->settings['temporaryLocation'];}
		
		# If the location is the temporary location (e.g. has been reset), give a message to that effect
		if ($location == $this->settings['temporaryLocation']) {$locationMessage = "Temporary (the webmaster will move the file and inform you)";} else {$locationMessage = $location;}
		
		# Create the form
		$form = new form (array (
			'displayDescriptions'	=> false,
			'displayRestrictions'	=> false,
			'showFormCompleteText'	=> false,
			'displayColons'			=> false,
			'submitButtonText'		=> 'Copy over file(s)',
			'developmentEnvironment' => $this->settings['developmentEnvironment'],
		));
		
		$form->heading ('p', 'Use this short form to copy file(s) across.');
		$form->heading ('p', 'Location: <strong>' . (($location != $this->settings['temporaryLocation']) ? '<a href="' . $location . '" target="_blank" title="(Opens in a new window)">' . $location . '</a>' : 'Temporary area') . '</strong>' . (isSet ($locationDisallowedMessage) ? $locationDisallowedMessage : ''));
		$form->upload (array (
			'elementName'			=> 'file',
			'title'					=> 'File(s) to copy over from your computer<br />(max. ' . ini_get ('upload_max_filesize') . 'B per submission</strong> of any number of files):',
			'uploadDirectory'		=> $_SERVER['DOCUMENT_ROOT'] . $location,
			'subfields'				=> $this->settings['uploadWidgets'],
			'presentationFormat'	=> array ('processing' => 'rawcomponents'),
			'minimumRequired'		=> 1,
			'enableVersionControl'	=> $this->settings['enableVersionControl'],
		));
		$form->input (array (
			'elementName'			=> 'name',
			'title'					=> 'Your name:',
			'required'				=> true,
		));
		$form->email (array (
			'elementName'			=> 'email',
			'title'					=> 'Your e-mail address:',
			'required'				=> $this->settings['emailAddressRequired'],
		));
		$form->checkboxes (array (
			'elementName'			=> 'informGroup',
			'valuesArray'			=> array ('Inform group'),
			'title'					=> 'Tick to have an e-mail sent to ' . $this->settings['groupDescription'] . ' informing them of the new file(s)',
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
				application::writeDataToFile ($logString, $this->settings['logFile']);
				
				# Build up an e-mail message
				$message  = "\n\nThe following was uploaded to the filespace by $name:";
				if ($notes != '') {$message .= "\n\n\nExplanatory notes:\n\n$notes";}
				$message  .= "\n" . $emailMessage;
				if ($location != '') {$message .= "\n\n\nLocation:\nhttp://" . $_SERVER['SERVER_NAME'] . str_replace (' ', '%20', $location);}
				
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
				if (!mail ($emailRecipient, $this->settings['emailSubject'], wordwrap ($message), $emailHeaders)) {
					echo '<p><strong>Although the file transfer was OK, there was some kind of problem with sending out a confirmation e-mail.</strong> Please contact the webmaster to inform them of the addition, at ' . $this->settings['administratorEmail'] . '.</p>';
				} else {
					# State that the e-mail has been sent and that it has (or hasn't) been logged
					echo '<p>An e-mail confirming this has been sent to ' . ($informGroup ? $this->settings['groupDescriptionBrief'] : $this->settings['ownerDescriptionBrief']) . ', and the change has been logged.</p>';
					if ($location == '') {echo '<p>The webmaster will move the upload to the correct place and send an e-mail accordingly.</p>';}
				}
			}
		}
	}
	
	
	# Function to create a new directory
	function createDirectory ($bannedDirectories = array (), $goToCreatedDirectoryAutomatically = false)
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
		
		# End if the location is not acceptable
		if (!$locationAcceptable) {return;}
		
		# Create the form
		$form = new form (array (
			'displayDescriptions'	=> false,
			'displayRestrictions'	=> false,
			'showFormCompleteText'	=> false,
			'displayColons'			=> false,
			'submitButtonText'		=> 'Create new directory',
			'developmentEnvironment' => $this->settings['developmentEnvironment'],
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
			'required'				=> $this->settings['emailAddressRequired'],
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
					application::writeDataToFile ($logString, $this->settings['logFile']);
					
					# Take the user directly to the new directory if required
					if ($goToCreatedDirectoryAutomatically) {
						# Send a header - will only work if output_buffering is switched on
						header ('Location: http://' . $_SERVER['SERVER_NAME'] . $location . $directoryName);
					} else {
						
						# Otherwise provide a link to the new location
						echo '<p>The directory was successfully created - <a href="' . $location . $directoryName . '/">go there now</a>.</p>';
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
		$location = (eregi (('^http://' . $_SERVER['SERVER_NAME']), $_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '/');
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