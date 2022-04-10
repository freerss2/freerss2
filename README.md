# Free RSS

Motivation
==========

During last years every one realized the importance of correct information sources. When we say "correct", we are not talking about the quality of one media or blog, but about a variety of opinions. Only by studying the maximum range of interpretations of the same event, one can make a balanced assessment of reality. FreeRSS, like other news aggregators, provides you such technical capability in modern-style and friendly way. It is up to you to decide whether to remain among the people subject to conspiracy theories and brainwashing, or to take your first step towards informational freedom.

About
=====

Free RSS2 is a successor to [Free RSS1 project](http://felixl.coolpage.biz/free_rss/). It helps to keep in touch with news, published on your favorite sites.

You don't need to travel over list of sites, trying to find something new and really interesting. RSS opens for everyone a new world, free of annoying ads, banners and irrelevant topics.

But even RSS is not ideal - sometime on the same news channel you can receive all topics as a mix: sport, politics, finance, entertainment... And several channels can cover the same topics in parallel. Naturally, you want to get all news of the same subject together, regardless of origin, while news about irrelevant topics should be simply muted.

This is the goal of Free RSS project: collect articles from different sites, filter-out unwanted content (by tags or keywords), and group the rest in handy "newspapers" - each on specific subject

Some terminology
================

Term "Feed" is a short of "RSS Feed". This way we'll refer a link where news could be downloaded, and also the articles, received from it

Well-organized websites are publishing such URL on their homepage. This link commonly looks like ![dot with radial stripes](https://www.iconsdb.com/icons/download/gray/rss-24.png "typical icon")

Articles are very similar to emails: it has title, origin and body. In addition, some articles could be marked with tags. Article titles allow to get a general idea of the content. By clicking on title, one can dive into the article content, or even follow the link and open original site in separate window.

For better management, the feeds could be arranged in "groups" by their origin, like: news, blogs, humor, general.

In addition, it is possible to create own "rules" for marking article with unobvious topic. For example, news-related site can publish some updates about finance, leisure, politics, entertainment, crime... The article tag (if exist) may serve as a pattern for creating respective rule, like: "mark as music if title match \*concert\* or tag = performers". Articles that have received such a classification will be displayed in so-called "watch" named "music". Also it's possible to define rules for removing articles, related to irrelevant topic ("trash" watch).

Requirements
============

Free RSS uses a browser-based GUI with design adaptive for screen-size. You can connect to this service from any place in the world, using any web browser, on any platform: desktop, mobile, tablet, smart-TV. Naturally, it should be a modern browser with HTML5 support and enabled dynamic content. Due to lack of compatibility, full functionality on IE and other MS browsers is not guaranteed.

Getting started
===============

There are two possible ways for filling-up personal list of subscriptions: import from OPML file or enter their links one-by-one.

If you know what's OPML - just go to "settings" and upload your OPML file. Please be careful - this operation removes all existing feeds definitions, so it could be used for recovery, but should not repeated in regular circumstances.

Now let's learn how to add single RSS subscription to FreeRSS reader. First, you've to copy the feed URL to clipboard. Then select from application menu "Add new RSS" and paste the URL in first textbox. You can add some informal title, but it's not critical: the original title will be read from RSS, and you can always rename it. The "group" selection is also optional, but it could be nice to place new RSS under right origin.

Keyboard shortcuts
==================

For making better user experience on bigger screen with large distances between control buttons, we added some keyboard shortcuts:

| Keystroke | Function |
| --------- | -------- |
| ArrowRight | Open current article |
| ArrowLeft | Collapse current article |
| Ctrl/ArrowRight | Go to next RSS |
| Ctrl/ArrowLeft | Go to previous RSS |
| Ctrl/ArrowDown | Mark current article as "read" and move to next one |
| Alt/H | Jump to service homepage |
| Ctrl/Z | Mark all articles on current page as "read" |

Technical details and source code
=================================

This is a FREE Open Source project. All code (excluding hosting-specific credentials) is available online on [GitHub](https://github.com/freerss2/freerss2)

The code is rewritten from scratch using only DB schema from Free RSS 1. Since this is online multy-user system, all tables got extra "user\_id" column. Naturally, the programming language was changed from Perl to PHP, and SQL engine from SQLite3 to MySQL. In addition, for getting modern look-n-feel style, all GUI part styled with latest [Bootstrap CSS-classes](https://getbootstrap.com/) and [FontAwesome](https://fontawesome.com/) icons set.

Contacting author
=================

Please see my contact email on [personal homepage](http://felixl.coolpage.biz/)
