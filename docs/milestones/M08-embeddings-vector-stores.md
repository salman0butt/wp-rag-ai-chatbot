# M08 — Embeddings & Vector Store Abstraction/Implementations

Status: NOT STARTED

## Goal
Generate compatible embeddings and store/search them through replaceable vector-store adapters.

## Dependencies
M03 provider capabilities, M07 chunks.

## In Scope
Embedding provider contract; batching/usage; compatibility fingerprint; local WordPress vector store; target external adapters Qdrant/Pinecone/Chroma/OpenAI Vector Store as milestone scope permits; health/capability/filter contracts.

## Out of Scope
Hybrid retrieval orchestration (M10).

## Architecture
Embedding service + VectorStore registry; collection/index compatibility prevents mixed model/dimension configurations.

## Acceptance Criteria
Embedding batches/error normalization pass; incompatibility forces controlled reindex; vector-store contract suite passes per implemented adapter; local-store scale limits documented and tested with bounded candidate behavior.

## Tasks
Pending plan; may split adapter implementations into subplans if needed.

## TDD Evidence
Pending.

## Integration Test Evidence
Adapter contract/integration required; external services may use opt-in containers/credentials.

## E2E / Visual Verification
N/A.

## Security Review
Credential isolation, vector namespace/tenant isolation, sensitive metadata.

## Accessibility Review where UI exists
N/A.

## Performance Review where relevant
Embedding batching and local-vector benchmark/limits.

## Code Review Findings
Pending.

## Fixes
Pending.

## Fresh Verification Commands
Pending.

## Fresh Verification Results
Pending.

## Commits
Pending.

## Files Changed
Pending.

## Known Limitations
Pending.

## Documentation Updated
Pending.

## Completion Checklist
All mandatory gates.

## Next Milestone
M09 — Job Queue & Synchronization.
