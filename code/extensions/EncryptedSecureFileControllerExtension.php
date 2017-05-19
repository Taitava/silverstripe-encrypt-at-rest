<?php

if (class_exists('SecureFileController'))
{
	/**
	 * Class EncryptedSecureFileControllerExtension
	 *
	 * If the secureassets module is installed, this class hooks into that module's asset download process and decrypts
	 * the downloadable asset file on the fly (if the asset is encrypted).
	 *
	 * This extension is applied automatically to SecureFileController, no custom code needed to call this class!
	 *
	 * Note that this class won't be defined at all if the secureassets module is not present!
	 */
	class EncryptedSecureFileControllerExtension extends Extension
	{
		/**
		 * @var File|EncryptedFileExtension
		 */
		private $file;
		
		/**
		 * @var AtRestCryptoService
		 */
		protected $service;
		
		public function __construct()
		{
			parent::__construct();
			$this->service = Injector::inst()->get('AtRestCryptoService');
		}
		
		/**
		 * Decrypts the downloadable asset and sends it to the web browser.
		 *
		 * This method hijacks the SecureFileController's handleRequest() method before it's going to output the asset
		 * file. This method won't return the execution back to the original method - unless the asset file is determined
		 * to be a normal, unencrypted file which does not need any decryption. In this case, execution is returned
		 * back to the original method to handle the file sending.
		 *
		 * @param File|EncryptedFileExtension $file A file object which should have been extended with EncryptedFileExtension.
		 * @throws Exception
		 */
		public function onBeforeSendFile(File $file)
		{
			$this->file = $file; //Set this before calling any internal methods
			if (!file_exists($file->getFullPath())) return; //Let the secureassets module handle displaying a 404 error message when the file does not exist in the filesystem
			if (!$this->isEncrypted()) return; //Let the secureassets module handle sending non encrypted files to the browser.
			if (SapphireTest::is_running_test()) return;
			
			$this->ModifyHeaders();
			
			try
			{
				$file->DecryptAndFlush();
			}
			catch (Exception $e)
			{
				//Revert headers in order to display an error message in the browser and not making the browser to download the error message to a file
				header('Content-type:text/plain');
				header('Content-Disposition:inline');
				
				//Well, this is not actually an error handler, so rethrow the exception
				throw $e;
			}
			die(); //Prevent the SecureFileController::handleRequest() method from continuing and resending the file.
		}
		
		/**
		 * Modifies headers and makes some other small preparations.
		 *
		 * The code inside this method is borrowed 2017-05-17 from SecureFileController::sendFile():
		 * https://github.com/silverstripe/silverstripe-secureassets/blob/master/code/SecureFileController.php
		 *
		 * @see SecureFileController::sendFile()
		 */
		private function ModifyHeaders()
		{
			$file = $this->file;
			$path = $file->getFullPath();
			
			//The borrowed code below is intact - meaning that it is easier to update if the original method's code gets updated.
			//FIXME: basename($path) should give the original file name, not the one ending with '.enc'.
			//FIXME: Content-Length should be removed (no way to calculate decrypted content size, the Crypto library does not offer a method for this without loading the decrypted content into memory, which I want to avoid)
			//FIXME: HTTP::get_mime_type($file->getRelativePath()) should be made to use the original file extension, not the '.enc' file extension.
			//----------
			
			$disposition = $this->config()->content_disposition;
			if (!$disposition) $disposition = 'attachment';
			
			header('Content-Description: File Transfer');
			// Quotes needed to retain spaces (http://kb.mozillazine.org/Filenames_with_spaces_are_truncated_upon_download)
			header(sprintf('Content-Disposition: %s; filename="%s"', $disposition, basename($path)));
			header('Content-Length: ' . $file->getAbsoluteSize());
			header('Content-Type: ' . HTTP::get_mime_type($file->getRelativePath()));
			header('Content-Transfer-Encoding: binary');
			
			// Ensure we enforce no-cache headers consistently, so that files accesses aren't cached by CDN/edge networks
			header('Pragma: no-cache');
			header('Cache-Control: private, no-cache, no-store');
			
			if ($this->config()->min_download_bandwidth)
			{
				// Allow the download to last long enough to allow full download with min_download_bandwidth connection.
				increase_time_limit_to((int) (filesize($path) / ($this->config()->min_download_bandwidth * 1024)));
			}
			else
			{
				// Remove the timelimit.
				increase_time_limit_to(0);
			}
			
			// Clear PHP buffer, otherwise the script will try to allocate memory for entire file.
			while (ob_get_level() > 0)
			{
				ob_end_flush();
			}
			
			// Prevent blocking of the session file by PHP. Without this the user can't visit another page of the same
			// website during download (see http://konrness.com/php5/how-to-prevent-blocking-php-requests/)
			session_write_close();
			
			//----------
			//Borrowed code ends
		}
		
		/**
		 * Used by ModifyHeaders().
		 * @return Config_ForClass|null
		 */
		private function config()
		{
			return SecureFileController::config();
		}
		
		/**
		 * Checks whether $this->file has the EncryptedFileExtension extension. If not, returns false. If it does,
		 * calls the file's isEncrypted() method and returns the result.
		 *
		 * @return bool
		 */
		private function isEncrypted()
		{
			if (!$this->file->hasExtension('EncryptedFileExtension')) return false; //Regular File
			return $this->file->isEncrypted();
		}
	}
}
