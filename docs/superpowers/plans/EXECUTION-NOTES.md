# Execution Notes

The ChatGPT chat runtime used for M01 does not expose a subagent dispatch interface and cannot resolve external package registries. ADR-017 and ADR-018 define the approved process adaptation: use Superpowers executing-plans inline and feature-branch GitHub Actions for dependency-backed RED/GREEN evidence while preserving all TDD/review/verification gates.
