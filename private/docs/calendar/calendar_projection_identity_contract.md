projection_id is canonical runtime identity.

projection_entity_id is compatibility-only.

Runtime code must not:

query by projection_entity_id
join by projection_entity_id
bind :projection_entity_id
key maps/collections by projection_entity_id
read $row['projection_entity_id'] internally

Allowed only at:

ingress normalisation
persistence compatibility
outbound API/view shaping

Known compatibility files:

private/framework/calendar/calendar_projection_resolver.php
private/framework/calendar/calendar_node_ensurer.php
private/framework/expression/character_next_beat_suggester.php