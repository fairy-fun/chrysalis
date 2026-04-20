# Framework migration inventory

| Element | Type | Old home | New home | Invariant | Enforcement | CI proof | Repair path | Status |
|---|---|---|---|---|---|---|---|---|
| Procedure registration wrapper rule | framework fact | `frameworks` row | PHP service + CI | only safe wrapper is public | PHP + CI | forbidden-call scan, registration path check | replace direct calls | planned |
| `fw_safe_register_system_procedure(...)` logic | stored procedure | DB | PHP service + CI | target exists, starts `fw_` | PHP | registration path check | route through service | planned |
| Directive rollout route/audit | framework fact | `frameworks` row | PHP service + CI | router/service owns approved path | PHP + CI | forbidden-call scan | route through service | planned |
| `validate_system_directive(...)` logic | stored procedure | DB | PHP validator + canonical builder + CI | exact text, existence, active registration | PHP + CI | directive drift check | regenerate canonical text | planned |