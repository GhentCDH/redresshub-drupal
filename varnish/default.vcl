vcl 4.1;

import std;

# Varnish in front of Drupal. Drupal (purge + varnish_purger) sends
# "BAN" requests with a Cache-Tags header when content changes. They are only
# accepted with the right X-VC-Purge-Key header (VARNISH_PURGE_KEY in .env).

backend default {
    .host = "drupal";
    .port = "80";
}

sub vcl_recv {
    if (req.method == "BAN") {
        if (std.getenv("VARNISH_PURGE_KEY") == "" ||
            req.http.X-VC-Purge-Key != std.getenv("VARNISH_PURGE_KEY")) {
            return (synth(403, "Not allowed."));
        }
        if (!req.http.Cache-Tags) {
            return (synth(400, "Cache-Tags header missing."));
        }
        # ban every cached page that has one of the given tags
        ban("obj.http.Cache-Tags ~ " + {"(^|\s)"} + req.http.Cache-Tags + {"(\s|$)"});
        return (synth(200, "Ban added."));
    }

    if (req.method != "GET" && req.method != "HEAD") {
        return (pass);
    }

    # never cache admin pages or logged-in users
    if (req.url ~ "^/(admin|user|batch|core/install\.php|update\.php)" ||
        req.http.Cookie ~ "SESS") {
        return (pass);
    }

    # anonymous users: cookies (e.g. analytics) don't affect the page
    unset req.http.Cookie;
    return (hash);
}

sub vcl_deliver {
    # internal headers, not needed by browsers
    unset resp.http.Cache-Tags;
    if (obj.hits > 0) {
        set resp.http.X-Varnish-Cache = "HIT";
    } else {
        set resp.http.X-Varnish-Cache = "MISS";
    }
}
