# IMPORTANT! Use madmatt/silverstripe-encrypt-at-rest instead of this one! This is just a fork with some new, untested features!

# silverstripe-encrypt-at-rest

This module allows data to be encrypted in the database, but be decrypted when extracted from the database, using a
secret key (hopefully) known only by the web server.

*Note:* This does not provide significant protection except in the case of database compromise. It should be used as
part of a layered security strategy. This is because the key is still available on the web server, so if remote code
execution is achieved by an attacker, they will be able to read both the database *and* the encryption key, thereby
decrypting the content.

*Note:* This module is not yet ready for real use, it's currently v0.0.1 material.

## Usage

In your DataObject, use EncryptedDBField, to have it encrypted. At this point, everything is stored as text.

Set a key in your `_ss_environment` file. 
 
 ```define('ENCRYPT_AT_REST_KEY', 'mysupersecretlonghexkeyhere1234567890');```




## TODO

- Make sure $this->value is _always_ the unencrypted value
- Clean up
- EncryptedEnum needs validation
- Extended testing
- Test if the value is actually encrypted, before trying to decrypt
- 
