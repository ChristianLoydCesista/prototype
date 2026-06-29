# Request Document Page Updates

**Current Status:** PHP syntax broken from partial edits

**Issues:**
- Line 17: Raw SQL text instead of query
- Icons not fully removed
- No "Not available" for other docs
- Navigation needs sidebar consistency

**Plan:**
1. **Fix syntax** - proper static $document_types array
2. **Highlight 2 docs** - special styling
3. **Others "Not available"** - grayed out/disabled
4. **Remove all icons** from document cards
5. **Navigation** - consistent sidebar links

**Test:** http://localhost/prototype/app/citizen/request_document.php

