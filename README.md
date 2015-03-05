# moodle-format_socialwall

The eCommunity Platform uses a new Moodle Course Format (called Socialwall) which alters the way a typical course looks and behaves. Most people use “Topic” or “Weekly” course formats in Moodle.

This is new course format offers a timeline, where teachers and students may post and/or comment on. It is fully integrated in Moodle, so teacher may:

* make different kind of posts (Alerts, sticky post)
* attach activities to timeline-posts (via Drag and Drop too)
* attach files and URLs to posts
* comment on posts or reply to comments

Students (depending on capabilites) may:

* make standard posts
* attach resources (files or links) to posts
* comment on posts or reply to comments
* view results and feedback of assignments in their timeline

The eCommunity-Package includes:
*	a new Course Format (called socialwall, format_socialwall)

Optional:
*	a new Filter (filter_urlresource) to alter the way a posted url-Resource will be displayed
*	a local plugin (local_filterurlresbak) to backup and restore the filter data (unfortunately moodle doesn’t support backup and restore for filter data).
*	a new Block to display upcoming events and alerts related to the course (block_alerts).
