# Location Role Vocabulary and Event Location Guide

## Purpose

This guide documents a managed vocabulary for event-location
roles and how to use it with the existing `calendar_event_locations`
table.

------------------------------------------------------------------------



------------------------------------------------------------------------

# Resulting Vocabulary

id                        code        label
  ------------------------- ----------- --------------------
location_role_primary     PRIMARY     Primary Location
location_role_secondary   SECONDARY   Secondary Location
location_role_tertiary    TERTIARY    Tertiary Location

------------------------------------------------------------------------

# Verify the Vocabulary

``` sql
SELECT
    id,
    code,
    label
FROM location_role_classvals
ORDER BY label;
```

------------------------------------------------------------------------

# Existing Event Location Schema

Verified columns:

``` text
calendar_event_locations
------------------------
id
calendar_event_id
place_id
role_id
subsequence_index
created_at
state_classval_id
```

------------------------------------------------------------------------

# Attach a Primary Location

``` sql
INSERT INTO calendar_event_locations (
    calendar_event_id,
    place_id,
    role_id,
    subsequence_index,
    created_at
)
VALUES (
    7,
    'PLACE-102',
    'location_role_primary',
    0,
    NOW()
);
```

------------------------------------------------------------------------

# Attach a Secondary Location

``` sql
INSERT INTO calendar_event_locations (
    calendar_event_id,
    place_id,
    role_id,
    subsequence_index,
    created_at
)
VALUES (
    7,
    'PLACE-103',
    'location_role_secondary',
    1,
    NOW()
);
```

------------------------------------------------------------------------

# Attach a Tertiary Location

``` sql
INSERT INTO calendar_event_locations (
    calendar_event_id,
    place_id,
    role_id,
    subsequence_index,
    created_at
)
VALUES (
    7,
    'PLACE-104',
    'location_role_tertiary',
    2,
    NOW()
);
```

------------------------------------------------------------------------

# View Event Locations with Role Labels

``` sql
SELECT
    cel.calendar_event_id,
    p.place_name,
    cel.role_id,
    lrc.label
FROM calendar_event_locations cel
LEFT JOIN places p
    ON p.place_id = cel.place_id
LEFT JOIN location_role_classvals lrc
    ON lrc.id = cel.role_id
WHERE cel.calendar_event_id = 7
ORDER BY cel.subsequence_index;
```

------------------------------------------------------------------------

# Example Result

``` text
Royal Ballroom Dance Society   Primary Location
Studio One                     Secondary Location
Costume Closet                 Tertiary Location
```

------------------------------------------------------------------------

# Notes

At the time this guide was written:

-   `calendar_event_locations.role_id` was verified to exist.
-   `location_role_primary` was observed in live data.
-   No existing location-role vocabulary table had been verified.
-   `location_role_classvals` is therefore a proposed normalization
    table for managing location roles consistently.
