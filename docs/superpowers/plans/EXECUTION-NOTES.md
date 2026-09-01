# Execution Notes

The ChatGPT chat runtime used for this project does not expose a subagent dispatch interface and cannot resolve external package registries. ADR-017 and ADR-018 define the approved process adaptation: use Superpowers executing-plans inline and feature-branch GitHub Actions for dependency-backed RED/GREEN evidence while preserving all TDD/review/verification gates.

## M02 WPCS API-name correction

The first M02 plan drafts used PSR-style camelCase for multi-word PHP method names. The repository's established WPCS configuration enforces WordPress snake_case method names and the first Task 1 PHP quality run (`33552571707`) failed specifically on that mismatch. Repository coding standards therefore override those concrete plan spellings. M02 execution consistently uses snake_case equivalents such as `database_name`, `charset_collate`, `get_var`, `get_row`, `get_results`, `insert_id`, `db_delta`, `table_exists`, `migrate_if_needed`, `find_by_key`, `find_by_id`, `paginate_by_source`, `delete_by_source`, and `with_id`. This is a naming correction only; interfaces and behavior remain as planned.
