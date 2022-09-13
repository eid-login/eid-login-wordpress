=== eID-Login ===
Contributors: eidlogin
Requires at least: 5.7
Tested up to: 6.0
Stable tag: 1.0.2
Requires PHP: 7.3
License: AGPL
License URI: https://www.gnu.org/licenses/agpl-3.0.html
Tags: authentication, security, eID

The eID-Login plugin offers an alternative login to WordPress, using an electronic identity (eID) like e.g. the German eID ("Personalausweis").

== Description ==
The eID-Login plugin allows to use the German eID-card and similar electronic identity documents for secure and privacy-friendly login to WordPress. For this purpose, a so-called eID-Client, such as the AusweisApp2 or the Open eCard App and eID-Service are required. In the default configuration a suitable eID-Service is provided without any additional costs.

== Installation ==
The plugin can be installed like every other WordPress plugin, which means directly from within the WordPress instance or via download.

For the configuration, a wizard with an integrated help section guides you through the different steps.

1. Select Identity Provider
For the usage of the eID-Login plugin a service which makes the eID accessible is needed. This service is called Identity Provider or in short IdP. You can choose to use the preconfigured SkIDentity service or select another service.

2. Configuration at the Identity Provider
At the Identity Provider your WordPress instance, which serves as Service Provider, must be registered. The process of registration depends on the respective Identity Provider. The information needed for registration is provided in step 2.

3. Connect eID
In order to use a German eID ("Personalausweis") or another eID for the login at WordPress, the eID must be connected to an user account.

== Frequently Asked Questions ==
A list of Frequently Asked Questions (in German language) can be found [on the homepage](https://eid.services/help.html).

== Screenshots ==
1. Overview over the concept of the eID-Login.
2. The login screen with eID-Login enabled.
3. The wizard that guides you through the configuration.
