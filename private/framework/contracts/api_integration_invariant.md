[[RULE: RULE-API-INTEGRATION-COMPLETION-001]]
title: API Integration Completion Rule
scope: repo_api
status: locked
rule_type: completion_invariant

statement:
A new API operation is not considered integrated when its handler file exists alone. It is only considered integrated when the full exposure chain has been updated and validated.

required_surfaces:

contract
router
handler
visibility
behaviour_tests
ci_enforcement

completion_requirements:

The operation must be declared in the canonical contract.
The operation must be routed in the API entrypoint.
The mapped handler file must exist at the declared path.
Visibility rules must be updated if the operation or its support files need to be auditable through the repo API surface.
Behaviour tests must be added or explicitly confirmed for the new operation if runtime behaviour changes.
CI must fail if declared integration is incomplete.

failure_rule:
An implementation file without corresponding contract or router registration is incomplete.
A routed operation without a declared contract entry is incomplete.
A declared operation without a resolvable handler is incomplete.
A behaviour-affecting operation without test coverage is incomplete.

interpretation:
“Implemented” does not mean “code written.”
“Implemented” means “contracted, routed, exposed as intended, and enforced by CI.”

[[RULE: RULE-API-INTEGRATION-CHECKLIST-001]]
title: API Operation Integration Checklist
scope: repo_api
status: locked
rule_type: operational_checklist

checklist:

Contract entry added
Router dispatch added
Handler file created
Visibility updated if audit exposure is required
Behaviour tests added or confirmed
CI validation passes end-to-end

usage_rule:
Any task phrased as “add operation”, “add endpoint”, “add function to API”, or equivalent must be interpreted as requiring this full checklist unless explicitly scoped otherwise.

[[RULE: RULE-API-REGISTRATION-NO-MEMORY-001]]
title: No Memory-Based Registration
scope: repo_api
status: locked
rule_type: framework_invariant

statement:
Registration steps for new API operations must not rely on human memory.

enforcement_rule:
The framework must prefer declarative registration plus CI validation over undocumented manual steps.

required_framework_behaviour:

Canonical operation declarations must live in one source of truth.
CI must validate router coverage against that declaration source.
CI must validate handler existence against that declaration source.
Missing registration must produce a hard failure, not a soft reminder.

[[INTERACTION: INTERACTION-API-IMPLEMENTATION-DEFAULT-001]]
title: Default Interpretation for New API Work
scope: nl_framework
status: locked

trigger_phrases:

add a new API operation
add a new endpoint
implement a new repo API function
wire in a new handler
expose a new capability through the API

default_interpretation:
Treat the task as:
contract + router + handler + visibility + CI behaviour coverage

exception_rule:
Only omit one or more surfaces if the user explicitly narrows scope.

assistant_behaviour:
The assistant should proactively check for missing registration surfaces rather than assuming the implementation file alone completes the task.