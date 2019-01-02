# wordpress-generate-static-site
Convert a WordPress VIP site to a static HTML site. Requires the Simply Static plugin.  

## Documentation  \
for  \
Static Site Generation


## Summary

rtCamp worked with PMC to convert two WordPress.com VIP hosted sites into static HTML sites and host them on AWS S3 bucket. This document mentions the process followed by rtCamp to generate the static sites.


## Process

1.  Setup WP site on intermediate server as per VIP Classic requirements (directory structure)
1.  Enable Multisite
1.  Request for following things from VIP team:
    1.  Database dump
    1.  Users table
    1.  All media under wp-content/uploads
1.  WP VIP team will provide the database dump excluding users table. They will provide the users data in CSV format or TSV format.
1.  Import the database dump in our intermediate server database and also import the users CSV. We got it in TSV format also after importing this we are going to get new user ids so we created a custom wp cli command to import users from TSV and assigned new user id to its respective posts authors.
    * The relevant code is provided in the included `functions.php` file. The functions from this file should be included in your WordPress site's `functions.php`.
1.  Place the media provided by the WP VIP team in our intermediate server's uploads dir
1.  Start regenerating media sizes in background [using WP-CLI](https://developer.wordpress.org/cli/commands/media/regenerate/) command. Use `screen` or `tmux` to keep them running in background even when you shutdown your local machine.
1.  Disable comments for all posts using following WP-CLI command: `wp post list --format=ids | xargs wp post update --comment_status=closed`
1.  Add shortcodes which are not supported outside the WP VIP environment. (Currently this only includes `protected-iframe`).
    * The relevant code is provided in the included `functions.php` file. The functions from this file should be included in your WordPress site's `functions.php`.
1.  "Embed shortcodes" needs to be enabled from Jetpack modules: [https://jetpack.com/support/control-jetpacks-modules-on-one-page/](https://jetpack.com/support/control-jetpacks-modules-on-one-page/)
    * A copy of Jetpack v5.8 (the version used when this method was tested) is included in this repository in case the latest version does not work properly.
1.  Install [Simply Static](https://wordpress.org/plugins/simply-static/) plugin on the intermediate server. Do the necessary settings and start static site generation.
    * A copy of Simply Static v2.1.0 (the latest at the time of this writing) is included in this repository in case the version on WordPress.org plugin repo no longer works.

---


END OF DOCUMENT
