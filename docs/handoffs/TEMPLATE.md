# Task Handoff

Copy this template into `docs/handoffs/` only when a handoff contains decisions,
evidence, or operational context that must remain durable beyond the active task.

```yaml
task_id: TASK-000
parent_task_id: null
phase: discovery
gate: G0_intake
from: orchestrator
to: implementation_engineer
status: requested
objective: Observable outcome to produce
scope:
  include: []
  exclude: []
inputs: []
expected_artifacts: []
acceptance_criteria: []
constraints: []
dependencies: []
evidence: []
risks: []
decisions: []
assumptions: []
approval_required: false
correction_attempt: 0
next_recipient: qa_reviewer
stop_condition: Measurable completion or blocking condition
```

## Status Lifecycle

```text
requested -> in_progress -> review -> accepted
    |              |           |
    v              v           v
cancelled       blocked   changes_requested -> in_progress
```

A handoff never contains secrets, personal data, large raw logs, or internal
chain-of-thought. It retains only the decisions, evidence, risks, and context
required for accountable continuation.
