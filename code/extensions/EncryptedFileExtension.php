<?php

/**
 * Class EncryptedFileExtension
 *
 * If you want to encrypt (some of) your assets, you have two options:
 * A)
 *   Create a custom class:
 * ```
 * class MyFile extends File
 * {
 *      $extensions = array('EncryptedFileExtension');
 * }
 * ```
 *
 * or B)
 *   Apply the extension directly to the File class via YAML config in mysite/_config/config.yml:
 * ```
 * File:
 *   extensions:
 *     - EncryptedFileExtension
 *```
 * @property File|Image|EncryptedFileExtension $owner
 */
class EncryptedFileExtension extends DataExtension
{
	/**
	 * @var AtRestCryptoService
	 */
	protected $service;
	
	public function __construct()
	{
		parent::__construct();
		$this->service = Injector::inst()->get('AtRestCryptoService');
	}
	
	public function onAfterWrite()
	{
		$this->Encrypt();
	}
	
	public function isEncrypted()
	{
		//TODO: Implement a more reliable way to determine whether the content itself of the file is encrypted.
		return 'enc' == strtolower($this->owner->getExtension());
	}
	
	/**
	 * Encrypts the file in the filesystem if it's not encrypted already.
	 * Note that the file name will change after this, as ".enc" will be added at the end of the file name.
	 */
	public function Encrypt()
	{
		if (!$this->isEncrypted())
		{
			$this->service->encryptFile($this->owner, $this->getKey());
		}
	}
	
	public function DecryptAndFlush()
	{
		if (!$input = fopen($this->file->getFullPath(), 'r')) throw new Exception(__METHOD__ . ': Can\'t download asset: file #' . $this->file->ID . ' cannot be read.');
		Defuse\Crypto\File::decryptResource($input, STDOUT, $this->getKey());
		fclose($input);
	}
	
	private function getKey()
	{
		$key = null; //Use the ENCRYPT_AT_REST_KEY key by default, if no custom key provider is defined.
		
		if ($this->owner->hasMethod('provideEncryptionKey'))
		{
			/* If the File object (or whatever similar class) has the provideEncryptionKey() method, use that
			 * as the primary source for the key. This method can return any of the following: a string, a null,
			 * or a Defuse\Crypto\Key object.
			 *
			 * If the return value is null, $this->service->getKey() will replace it with a Key object derived
			 * from the default encryption key defined in the ENCRYPT_AT_REST_KEY constant.
			 *
			 * If the return value is a string, $this->service->getKey() will convert it to a Key object.
			 */
			/** @var null|string|Defuse\Crypto\Key $key */
			$key = $this->owner->provideEncryptionKey();
		}
		
		//Use the service->getKey() to make sure that we have a Defuse\Crypto\Key object
		return $this->service->getKey($key);
	}
	
	/**
	 * Encrypted files have '.enc' added at the end of their names. This method returns the filename without the '.enc'
	 * part.
	 *
	 * @return string
	 */
	public function getOriginalFilename()
	{
		return preg_replace('/.enc$/i', '', $this->owner->getFilename());
	}
	
	/**
	 * Encrypted files have '.enc' added at the end of their names, which would make the normal getExtension() method
	 * to always return 'enc'. This method first removes the 'enc' part and then uses the SilverStripe's original method
	 * to get the real, original extension, i.e. 'txt', 'jpg' etc.
	 *
	 * @return string
	 */
	public function getOriginalExtension()
	{
		return File::get_file_extension($this->getOriginalFilename());
	}
	
}