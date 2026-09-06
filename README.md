# Cozmic Core

The floor every Cozmic client website stands on: the content model, the client
role, the options, the admin governance, and the structural SEO.

- **Requires:** WordPress 6.7+, PHP 8.2+
- **Plugin slug / directory:** `cozmic-core` (must match, see below)
- **Text domain:** `cozmic-core` · **PHP prefix:** `cozmic_core_`
- **Architecture + decisions:** `docs/wordpress-product-architecture.md` in the
  `cozmic-platform` repo. Read D3, D4, D8 and D9 before changing anything
  structural here.

## The boundary

Three artifacts, and the test for which one a thing belongs in is **"what breaks
if this is removed?"** (D9), not "what kind of thing is it."

| Layer | Contains | Removable? | If removed |
|---|---|---|---|
| **Cozmic Core** (this) | post types + meta, roles, options, admin governance, Gravity Forms defaults, structural SEO | **Never** | The content model collapses |
| **Cozmic Block Theme** | `theme.json`, templates, patterns, block styles | It is the theme | Design goes; content survives as core blocks |
| **Cozmic Connect** | REST endpoints, telemetry, form forwarding | Yes | Dashboard goes blind; the site is fine |

> Plugin = what exists and what it does. Theme = how it looks and where it goes.

If removing a file would cost the client *content*, it belongs here. If it would
only change how that content looks, it belongs in the theme.

## Layout

```
cozmic-core.php   Header, constants, requires, activation
inc/options.php   The two option rows and their accessors
inc/post-types.php  Services, Events, Portfolio
inc/meta.php      The field schema, registration, and the editor sidebar panel
inc/bindings.php  The cozmic/field Block Bindings source (formatted values)
inc/roles.php     The cozmic_client role (D8)
inc/admin.php     Admin governance: block locking, menu and dashboard tidying
inc/settings.php  The two settings screens, both REST-exposed
inc/seo.php       Meta description, LocalBusiness, FAQPage, Event
inc/gravity-forms.php  Notification sender fix + honeypot default
inc/updates.php   Plugin Update Checker wiring
assets/js/        The editor sidebar panel (no build step, plain wp.* globals)
```

## The content model

Three post types, each gated by a setting and **off by default**, matching the
platform. Blog posts are core `post`; products are deferred with the commerce
plugin (D5).

| Type | Key | URL | REST |
|---|---|---|---|
| Services | `cozmic_service` | `/services/` | `/wp/v2/services` |
| Events | `cozmic_event` | `/events/` | `/wp/v2/events` |
| Portfolio | `cozmic_project` | `/portfolio/` | `/wp/v2/portfolio` |

A type that is switched off is not registered, which hides it from the admin,
the front end, and the menu editor at once. **Nothing is deleted** - the rows
stay in `wp_posts` and come back intact when it is switched on again.

### Fields

Bodies are block markup in `post_content`; the scalars beside them are post meta
(D3). `cozmic_core_field_schema()` in `inc/meta.php` is the single source of
truth - registration, the editor panel, and the bindings source all read from
it, so **a new field is one entry there** and shows up in all three.

| Field | On | Type |
|---|---|---|
| `cozmic_cta_label` / `cozmic_cta_link` | services, events, portfolio | text / url |
| `cozmic_start_date` / `cozmic_end_date` | events | datetime (`Y-m-d\TH:i`) |
| `cozmic_location` | events | text |
| `cozmic_project_url` | portfolio | url |
| `cozmic_meta_description` | everything public | textarea |

Keys carry **no leading underscore** on purpose. WordPress treats an
underscore-prefixed key as protected and core's `core/post-meta` binding source
refuses to read protected meta, so a pattern bound to `_cozmic_cta_label` would
render blank forever with nothing in any log to explain it.

### Binding a pattern to a field

Use **`cozmic/field`**, this plugin's source, not core's `core/post-meta`:

```json
{"metadata":{"bindings":{"content":{"source":"cozmic/field",
  "args":{"key":"cozmic_start_date","format":"datetime"}}}}}
```

`format` is `date`, `time`, or `datetime`, and follows the site's own format and
timezone settings.

**The difference that matters is the empty case.** Every field here is
registered with a default of `''`, so `core/post-meta` on an unset field returns
an empty string and the bound block renders blank - an empty button, a heading
with no words. `cozmic/field` returns null instead, which tells core to leave the
block's own content alone. So the markup you write **is** the fallback, and it
should be written as a good default rather than as a hint to the editor: the
service template's button says "Get a quote" pointing at `/contact`, which is
right for any service whose CTA fields were never filled in.

Where there is no sensible default - a project's external URL, which many
projects simply do not have - leave the template's `href` empty and put
`cz-optional-link` on the button. The theme hides it with `:has()`. A block
template has no conditionals, so this is the seam that stands in for one.

### Computed fields

Some values are composed rather than stored, for the same "no conditionals in a
template" reason. Bind them exactly like a stored field.

| Key | Produces |
|---|---|
| `cozmic_event_details` | The whole when-and-where line: `September 12, 2026 6:30 pm to 8:00 pm · Augusta Armory`. An end time on the same day drops the repeated date; midnight is treated as all-day and shows no time; missing pieces are left out, and if nothing is set it returns null so the template's fallback stands. |

Binding a date and a location to two separate blocks was the alternative, and it
puts a stray label on every event missing one of them. Composing the line in PHP
puts the "if" where ifs can go.

Bindings only reach the attributes core supports: paragraph and heading
`content`, image `url`/`alt`/`title`, and button `text`/`url`/`linkTarget`/`rel`.

## Settings

Two screens under one **Cozmic** menu, split by who may write them - the same
line D8 draws through the product.

| Screen | Capability | Option | REST key |
|---|---|---|---|
| Business Info | `edit_pages` (the client) | `cozmic_core_business` | `cozmic_business` |
| Site Setup | `manage_options` (Cozmic) | `cozmic_core_options` | `cozmic_setup` |

Both are registered with `show_in_rest`, so **Cozmic Connect can push a business
profile at provisioning over `/wp/v2/settings`** rather than this plugin growing
a bespoke endpoint with its own auth to get wrong. Writes merge over what is
stored, so a partial PATCH updates one field without resetting the nine it did
not send.

The business keys mirror the platform's `sites` columns one for one, so a
provisioning push is a copy rather than a translation.

## The client role

`cozmic_client` is an editor's content capabilities minus everything that can
change what the site *is*. The capability that matters is `edit_theme_options`,
because WordPress gates Global Styles, the Site Editor, **and** the block-unlock
affordance all on it. Withholding it is what makes a rented site rented;
granting it with a real Administrator account is what a client buys.

**Known consequence, stated plainly:** on a block theme the navigation menu
lives in a template part, so a client on this role **cannot edit their own
menu**. There is no partial grant - the capability is shared with everything
above it. Menu changes route through Cozmic on rented sites, exactly like
styling. A purchased site gets an administrator and the question disappears.

The capability list is versioned (`COZMIC_CORE_ROLE_VERSION`). `add_role()`
writes once and no-ops forever after, so without the version a capability added
in a later release would reach new sites only and the fleet would drift apart
one release at a time. Reconciliation is authoritative in both directions: a
hand-edited role is reset, not respected.

## Structural SEO

Emitted here rather than in the theme so it survives a theme switch, and so a
WordPress client does not get a worse audit result than a platform client for a
reason nobody can see.

- `<meta name="description">` from `cozmic_meta_description`, falling back to a
  hand-written excerpt but **never** to an auto-generated one
- `LocalBusiness` JSON-LD from the business profile, honouring "keep the address
  private" while still publishing the service area
- `FAQPage` JSON-LD built from core Details blocks - the hook the theme's FAQ
  pattern is written against, and the reason that pattern is not structurally
  invisible. Two pairs minimum
- `Event` JSON-LD on single events, from the date and location meta

**All of it stands down when a real SEO plugin is active** (Yoast, Rank Math,
AIOSEO, SEOPress, Slim SEO, The SEO Framework). Two plugins both emitting
LocalBusiness is worse than neither. The FAQ and Event data keep running, because
no SEO plugin builds either from core blocks or Cozmic meta, so nothing collides.

## Gravity Forms

Two defaults, both filterable:

- **Notification sender.** Gravity Forms defaults a notification's From address
  to the address the visitor typed in. That is now the most common reason a
  client stops receiving leads: the mail claims to come from gmail.com, the
  sending server cannot prove it speaks for gmail.com, and SPF/DMARC bin it. The
  From address becomes the site's own domain and the visitor's moves to
  Reply-To, so hitting reply still reaches them. A notification already sending
  from the business's own address is left alone.
- **Honeypot on**, matching the platform's no-CAPTCHA posture.

Form *entries* reaching the dashboard is Connect's job, not this plugin's.

## Installing

The directory name is the plugin slug, and Plugin Update Checker identifies the
plugin by it, so **the folder must be `cozmic-core` wherever it is deployed** -
which is also the repo name, but never rely on that:

```bash
git clone <repo-url> cozmic-core
```

Drop it in `wp-content/plugins/` and activate. Activation seeds both option rows,
creates the client role, and flushes rewrite rules.

There is no local environment by decision (D7): development happens on the
Cloudways staging app, validation on the canary, releases ship as GitHub Release
tags.

## Releasing

Same machinery as the theme.

1. Bump `Version:` in `cozmic-core.php` **and** `COZMIC_CORE_VERSION` - PUC
   compares against the header, so a forgotten bump means the update is silently
   never offered.
2. Commit, push, tag a GitHub Release.
3. Sites pick it up within PUC's check window (~12h), or immediately via
   Plugins > "Check for updates".

No release assets: the repo root is the plugin root and there is no build step,
so GitHub's source zip is already a valid package. `export-ignore` entries in
`.gitattributes` are excluded from it.

**Until the first Release is tagged, PUC finds nothing.** Expected, not broken.

**Keep auto-updates off on client sites at first.** A bad tag with auto-update on
reaches every client at once.

## Deliberate omissions

- **No uninstall routine.** A client's content outliving the plugin is correct
  behaviour. Deactivation flushes rewrite rules and leaves the role in place -
  removing it would strip every client user of their capabilities the moment the
  plugin is toggled off, turning a reversible mistake into a lockout.
- **No custom blocks** (D4). Patterns of core blocks degrade to core blocks; a
  custom block deactivated is "this block contains unexpected content" across a
  client's site, which contradicts D2 outright.
- **No styling.** D1 hands Global Styles to the client at build and never writes
  it again.
