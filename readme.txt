=== Lazy Moderator ===
Contributors: Phan An
Donate link: http://www.phoenixheart.net/wp-plugins/lazy-moderator/
Tags: comment, approve, moderator, instant, lazy
Requires at least: 2.8
Tested up to: 4.0
Stable tag: 1.1.1

Comment moderation for the lazy! Provides quick-yet-secure one-click links to moderate comments.

== Description ==
This is how you moderate comments with WordPress this far: First you receive an email from WordPress notifying about a new comment (whoohoo!) Eagerly, you click the "Approve it" link. A new tab opens with a form asking you to log into the administrator area. You input the username and that long long password, click "Log Me In." There, you are welcomed with a silly "Are you sure?" confirmation message and two buttons: "Yes I'm sooooo sure" and "No I'm not sure at all, my $200 mouse was just malfunctioning."

Ever felt that it's a little too much? Well, if you are that lazy (just like I am), then Lazy Moderator is just for you! Now the notification email will be appended with real "one-click" links to help you moderate the comment with just, well, one click. Yes, one click and it's done. No login. No confirmation. No hassles. No shit.

== Installation ==
1. Download and extract the plugin
1. Upload the entire `lazy-moderator` folder to the `/wp-content/plugins/` directory
1. Activate the plugin via "Plugins" panel inside wp-admin area
1. ....?
1. Profit!

== Frequently Asked Questions ==
= Is this plugin secure? I mean, I don't even have to log in to moderate comments? How is this possible? =
Well, this depends on how "secure" you want it to be. Each moderation action is associated with a unique, random, irreversible 40-character long string (called "token"). As a blog owner/author, you have these tokens attached in the moderation links, ready for you to use. Now, in order to moderate the comments like you do, another person must make a guess. If he's lucky enough to guess a 40-character long string perfectly and successfully hacks into your system, DON'T SUE HIM! Instead, make friends with him and ask him to buy you some lottery tickets.
= What's that with all the drawings after moderation? =
Most of them are [rage comics](http://www.reddit.com/r/ragecomics/). I like them a lot, so I decided to use them for some personality.

== Screenshots ==
1. "Token validation failed" error
2. "Comment approved" message
3. "Comment already approved" message
4. "Comment not found" error

== History ==
* 1.1.1
1. Minor bug fixes
* 1.1.0
1. wpdb error fixed
* 1.0.1
1. Minor spelling mistakes fixed
* 1.0.0
1. First version