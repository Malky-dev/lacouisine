# Adaptive Orchestration Workflow

## Operating Principle

The orchestrator selects the smallest set of agents that covers the assigned
scope and material risks. Optional specialists may be skipped only with an
explicit rationale. A gate passes only when its acceptance criteria have current,
verifiable evidence.

## Lifecycle Gates

| Gate | Objective | Typical owners | Minimum evidence |
|---|---|---|---|
| `G0_intake` | Establish the objective, scope, constraints, and unknowns | `orchestrator`, human owner | Brief, boundaries, constraints, open questions |
| `G1_product` | Approve the problem, outcomes, priorities, and acceptance criteria | `product_manager`, optionally `user_researcher` | Requirements, priorities, acceptance criteria, research evidence when required |
| `G2_design` | Make the solution implementation-ready | `ux_ui_designer`, `solution_architect`, `data_engineer`, `security_engineer` as required | Journeys, decisions, contracts, threat analysis, required approvals |
| `G3_build` | Produce a verifiable increment | `implementation_engineer`, `data_engineer`, `devops_engineer` as required | Changes, local validation, migrations, delivery automation |
| `G4_verify` | Independently evaluate the increment | `qa_reviewer`, `security_engineer` as required | Verdicts, executed checks, defects, residual risks |
| `G5_operate` | Prepare operation, recovery, and durable knowledge | `documentation_writer`, `devops_engineer`, `site_reliability_engineer` as required | Documentation, deployment and rollback procedures, observability, runbooks |
| `G6_release` | Consolidate release readiness for a human decision | `release_manager`, human owner | Release manifest, gate matrix, known risks, human decision |

## Adaptive Selection Rules

- Use `user_researcher` when a material user assumption lacks evidence.
- Use `ux_ui_designer` for user-visible experiences or interactions.
- Use `solution_architect` for new boundaries, dependencies, or structural
  decisions.
- Use `data_engineer` for schemas, migrations, pipelines, quality rules, or data
  lifecycle changes.
- Use `security_engineer` for identity, permissions, secrets, sensitive data,
  network exposure, or material security risk.
- Use `devops_engineer` for builds, environments, delivery, or infrastructure.
- Use `site_reliability_engineer` for operated services, reliability objectives,
  observability, capacity, or incident response.
- Use `documentation_writer` whenever a decision, interface, or procedure must
  remain durable beyond the active task.
- Use `release_manager` only for a uniquely identified release candidate.

`product_manager`, `implementation_engineer`, and `qa_reviewer` form the minimum
path for a product behavior change. The orchestrator and human owner govern every
path.

## Parallel Execution

No more than three assignments may run concurrently. Parallel work is allowed
only when inputs are stable, assignments have no unresolved dependency on each
other, and agents cannot overwrite the same artifacts. Parallel results converge
into an evidence-based handoff before the next gate.

## Correction Loop

1. The reviewer identifies the failed criterion and provides evidence.
2. The orchestrator returns a bounded correction assignment to the responsible
   author.
3. An independent reviewer validates the correction.
4. After three failed attempts at the same gate, the orchestrator stops the loop
   and asks the human owner to reduce scope, change the approach, explicitly
   accept an eligible risk, or stop the work.

An author never validates its own correction. Missing, stale, contradictory, or
unverifiable evidence never counts as passing.

## Required Human Approvals

Human approval is mandatory for:

- material scope, budget, or architecture changes;
- paid services, consequential external dependencies, or irreversible actions;
- acceptance of high or critical risk;
- sensitive external writes, destructive actions, or production data access;
- merging `develop` into `main`;
- production deployment or production modification.

## Final State

The orchestrator ends with `completed`, `blocked`, or
`awaiting_human_approval`. It cites the supporting artifacts and evidence,
identifies residual risks and required decisions, and names the next responsible
party. The `documentation_writer` persists durable decisions and operationally
useful state in version control.
