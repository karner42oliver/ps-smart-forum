# Quick Forum

[Deutsch](README.md) | **English**

[![Version](https://img.shields.io/badge/Version-1.0.0-2271b1?style=flat-square)](readme.txt)
![PHP](https://img.shields.io/badge/PHP-8.0%2B-777bb4?style=flat-square&logo=php&logoColor=white)
![WordPress](https://img.shields.io/badge/WordPress-21759b?style=flat-square&logo=wordpress&logoColor=white)
![ClassicPress](https://img.shields.io/badge/ClassicPress-03768e?style=flat-square)
[![License](https://img.shields.io/badge/License-GPL--2.0--or--later-2ea44f?style=flat-square)](https://www.gnu.org/licenses/old-licenses/gpl-2.0.html)

A forum right on your website: Quick Forum lets you embed discussions in pages
or posts using a shortcode. There is no need for a separate forum portal.

**Version: 1.0.0**

## What Quick Forum offers

- Multiple forums with their own names, descriptions, and colors.
- Topics and replies right on your website.
- Search within each forum.
- Post previews before submitting.
- Pinned and closed topics.
- Direct links to the latest reply.
- Configurable permissions for editing topic titles.
- A responsive interface that works on smartphones.
- Multisite support with separate forums for each website.

## Installation

You need WordPress or ClassicPress and PHP 8.0 or later.

1. In your administration dashboard, go to **Plugins → Add New → Upload Plugin**
   and upload the plugin ZIP file. Alternatively, copy the plugin folder into
   `wp-content/plugins/`.
2. Activate **Quick Forum**.
3. Open the new **Forums** menu.

On a Multisite installation, you can network-activate the plugin from the
Network Admin dashboard.

## Your first forum

1. Go to **Forums → New Forum**.
2. Enter a **Forum Name** and, optionally, a description.
3. Choose the colors and border width.
4. Click **Save**.
5. Copy the shortcode from the forum overview.
6. Paste it into a page or post and publish it.

Example:

```text
[forum id="123"]
```

Replace `123` with your forum's ID. The easiest option is to copy the ready-made
shortcode from the overview rather than typing it yourself.

### Multiple forums on one page

You can embed different forums on the same page:

```text
[forum id="123"]

[forum id="456"]
```

Searches, forms, and pagination remain separate for each forum.

## Topics and replies

Visitors must be logged in to create topics and replies. On a Multisite
network, they must also be members of the respective website.

- **Create a topic:** Open the page containing your forum and choose the option
  to create a new topic. Enter a title and your post.
- **Reply:** Open a topic and use the reply form.
- **Preview:** Check your post before submitting it. Previewing does not save
  anything.
- **Search:** Use the search field to find posts within the current forum.
- **Latest reply:** The corresponding link in the topic overview takes you
  directly to the latest post, even if it is on a later page.

Authors can edit their own posts within 30 minutes. Administrators are not
restricted by this time limit.

Previews require JavaScript. Topics and replies can still be submitted without
JavaScript.

## Moderating topics

Moderation features are available to users with administrator permissions on
the respective website.

- **Pin:** Keep important topics above other topics.
- **Unpin:** Remove a topic's pinned status.
- **Close:** Prevent new replies to a topic.
- **Open:** Allow replies to a closed topic again.
- **Edit:** Correct posts and topic titles.
- **Delete:** Remove topics or individual posts. The last post in a topic cannot
  be deleted on its own; delete the topic instead.

## Settings

Under **Settings → Forums**, you can choose:

- How many topics appear per page.
- How many posts appear per page.
- How many forums can be created per website.
- Who can edit topic titles.

There are three options for **Edit topic titles**:

| Setting | Who can change the title? |
|---|---|
| Moderators only | Website administrators only. |
| Authors for 30 minutes, moderators at any time | Topic authors within the first 30 minutes, administrators at any time. This is the default setting. |
| Authors and moderators at any time | Topic authors can edit their own titles at any time; administrators can edit all titles. |

On Multisite, these settings are under **Network Admin → Settings → Forums**
and apply to the entire network.

## Customizing the appearance

Open **Forums** and click **Edit** next to the forum you want to customize.
You can change its name, description, main color, secondary color, header color,
border color, and border width.

The color picker shows your selected color immediately. Choose sufficient
contrast to keep text and links easy to read.

## Frequently asked questions

### Why can't I post?

Log in first. On Multisite, your account must be a member of the respective
website. If you cannot reply to a particular topic, check whether it is closed.

### Why can't I create another forum?

You may have reached the configured maximum number of forums. Check
**Settings → Forums**, or the corresponding settings in Network Admin.

### Why isn't my forum showing up?

Check that the plugin is active and that the ID in your shortcode matches the
forum overview. The forum must belong to the website where you embed it.

### What happens when I remove a forum?

Under **Forums → Remove**, confirming the deletion removes the forum and all
its topics and posts. Afterwards, remove its shortcode from any affected pages.
Back up your website before deleting large amounts of content.

## License

Quick Forum is free software licensed under GPLv2 or any later version.
