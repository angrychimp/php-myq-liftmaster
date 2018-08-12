# php-myq-liftmaster
PHP scripts to control basic open/close functions for a MyQ garage door or Lamp/Light module.

### Instructions for Use
After cloning the repo, copy the `sample.config.ini` to `config.ini`, and edit the new file to add your username/email and password that you would use to connect to MyQ. Then run the included `use_MyQ_api.php` script to verify connectivity. From there, feel free to use the `MyQ.php` class file in whatever project you'd like.

## TO-DO
Finally coming back to this after a long hiatus since my other solution (a Wink hub) stopped working suddenly. I've finish the code to allow the door to be controlled via the class, and added some basic status reporting functionality. Next on the list will be:
1. Listing door devices
	Done: use_MyQ_api.php status
2. Allowing door selection via name or device ID
	Done: use_MyQ_api.php id=id# or name="device name"
3. Allowing additional door selection via location name (if you have doors with identical names at two or more locations)
