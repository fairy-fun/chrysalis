# Expression And Domain Subsystem Status

## Expression Authority

The sole authoritative expression resolver stack is:

- `private/framework/expression/expression_candidate_reader.php`
- `private/framework/expression/expression_output_resolver.php`
- `public_html/pecherie/chill-api/expression/resolve_character_expression_output.php`

`public_html/pecherie/chill-api/character/resolve_expression_output.php`
is a compatibility shim only.

It must delegate to the expression resolver stack and must not introduce
independent resolution logic.

`private/framework/character/resolve_expression_output.php`
has been retired as duplicate non-authoritative logic.

## Domain Mapping Status

`attribute_domain_map` remains active runtime authority for expression-domain
filtering.

It is still used by the expression resolver stack and is not a retirement
candidate at this time.

## Retirement Candidates

The following surfaces are retirement candidates and should not be treated as
active runtime authority unless a live consumer is reintroduced:

- `profile_type_domain_map`
- `attribute_domains`
- `v_attribute_domain_violations`

Current repo-visible state:

- `profile_type_domain_map` is CI/audit-only
- `attribute_domains` is audit-classification-only
- `v_attribute_domain_violations` has no repo-visible consumer

## Operational Rule

Do not infer runtime authority from audit inclusion alone.

For this subsystem:

- expression authority lives in the `expression` resolver stack
- `attribute_domain_map` remains live
- retirement-candidate tables/views remain tracked only for transition safety
