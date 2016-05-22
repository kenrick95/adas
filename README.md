# adas
>  https://tools.wmflabs.org/adas/

Administrator dashboard modules for Indonesian Wikipedia.

## Modules
Currently only contains one module.

### Daily Summary

Features:
- Return list of articles created on certain date, with their subsequent edits on that date
- Also with the likelihood of the edits being reverted (via [ORES](https://ores.wmflabs.org/)).

Technically:
- SQL query on `recentchanges` table of `idwiki_p` database.
- Example: https://quarry.wmflabs.org/query/9566

## License
MIT License
