php-sendmail-sendgrid
======================

------------------------------------------------------------------------

Overview
---------

This is a PHP CLI script to implement a sendmail-compatible interface to the Twilio SendGrid API.  The target is smaller systems with PHP and Curl, wanting to send a few system emails to administrators, but lacking an available SMTP relay.

This tool can work in combination with Twilio's SendGrid free plan to allow smaller systems or home users to send system status emails.  If desired, it could also work with their larger plans to integrate with some standard deployment of embedded systems.

For example, DD-WRT includes PHP and Curl, but the busybox sendmail binary would require an SMTP relay server for it to send emails.  This tool provides a solution for such a situation.

Configuration / Usage
----------------------

There is an example `php.ini` file included in this distribution.

Explaining the `php.ini` options:

| Option   | Description |
| -------- | ----------- |
| `cainfo` | Needed for Curl to find the CA information and allow connections to SendGrid via HTTPS. |
| `sendmail_path` | Set to the location of this binary to allow PHP's `mail()` call to use this script. |
| `add_x_header=1` | I like knowing where the email was generated, but since that is not ideal across all use cases, this is optional. |
| `sendgrid_apikey` | This is where one can define their SendGrid API key in a moderately secure fashion.  The alternative less secure option is specifying it in the `sendmail_path` command with the `-apPASSWORD` option, but that makes the key visible to any users of the system with access to the process info, which could pose significant security risks in some environments. |

This tool was used in an embedded DD-WRT device with persistent flash storage mounted at `/jffs/` to allow sending mail from scripts easily via SendGrid.  In this situation, I also installed a modified version of the `example_php.ini` as `/jffs/etc/php.ini`, which the system conveniently autoloaded on php startup.  The `sendmail.php` was copied to the system as `/jffs/bin/sendmail.php`, and the `sendmail_path` option in the `php.ini` was adjusted to point there accordingly.

Note: You can view the PHP INI search path by running: `php --ini` from the CLI.  Your INI modifications need to be in one of the listed INI file locations.

Command Line Arguments
-----------------------
```
usage: sendmail.php [-h|-t|-v|-i] [-f FROM] [-ap APIKEY] [{-o|-am|-au} DUMMY] [TO...]

  -h --help   This help text.

  -t          Recipients are gathered from command line as well as TO headers.
              This is always done, but the flag is allowed for compatibility.

  -v          Verbose flag increases the output for debugging and such.

  -f FROM     Specify the required FROM email address, forcing emails with a
              different FROM address header to fail.  This option allows emails
              to include a "FROM" header with a value matching this one, or to
              omit the header entirely, using this value instead.

  -ap APIKEY  Specify the Twilio SendGrid API key for authentication.  The code
              checks the PHP INI option 'sendgrid_apikey' so one can specify
              the API key there instead if preferred.  If both are defined,
              this argument takes presidence.

  TO...       List of 0 or more space-separated destination email addresses.

  Any flags that require an additional argument can be packed with the argument
  value, omitting the whitespace between them.  So, for example, "-apAPIKEY" is
  the same as "-ap APIKEY".

  The remaining options are accepted for compatibility, but are ignored.
```

Licensing / Changes / Collaboration
------------------------------------

For maximum flexibility, I have released this under the MIT license, but you can contact me if you need different licensing terms for some reason.

While not required, it would be nice to know if this project is used in other larger projects.  Please email me to let me know if this was of use to your project!

Pull requests with changes and improvements are appreciated.

---

Brian Gisseler (gissf1@gmail.com)
