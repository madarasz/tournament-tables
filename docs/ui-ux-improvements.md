# UI/UX Improvements

**Last Updated**: 2026-01-16
**Project**: Tournament Table Allocation System

## Purpose

This document tracks UI and UX improvements for the tournament tables application. It serves as:
- A **learning repository** of examples and patterns
- A **guideline** for future improvements
- A **record** of research and decision-making process

Each improvement should be well-researched, documented, and generalized to inform future decisions.

## Research Methodology

Before implementing UI/UX changes, always:

1. **Research Best Practices**: Search for established UX patterns and principles
   - Look for academic research (Nielsen Norman Group, Baymard Institute)
   - Study industry standards and design systems (Material Design, Apple HIG, Shopify Polaris)
   - Review UX blogs and case studies (Smashing Magazine, UX Collective, A List Apart)

### Research Completed ✅

Research conducted on **2026-01-16**. Key findings have been integrated into the improvement sections below.

#### 1. Post-Creation Flow Research

**Key Sources**:
- [Success Message UX Examples & Best Practices](https://www.pencilandpaper.io/articles/success-ux) - Pencil & Paper
- [Success States in Onboarding](https://www.useronboard.com/onboarding-ux-patterns/success-states/) - UserOnboard
- [Login & Signup UX Guide 2025](https://www.authgear.com/post/login-signup-ux-guide) - Authgear

**Key Findings**:
- **Full-page confirmations** are appropriate for critical, high-stakes actions (purchases, important submissions)
- **Inline/toast confirmations with redirect** are preferred for routine creation actions to maintain flow momentum
- Always provide clear feedback that the action succeeded before transitioning
- Redirect destination should align with user's next likely action
- Success states fall into three types: **Confirmation** (did it work?), **Context** (where am I now?), and **Celebration** (meaningful accomplishments)

#### 2. Progressive Disclosure & Smart Defaults

**Key Sources**:
- [Progressive Disclosure](https://www.nngroup.com/articles/progressive-disclosure/) - Nielsen Norman Group
- [Progressive Disclosure Types & Use Cases](https://blog.logrocket.com/ux-design/progressive-disclosure-ux-types-use-cases/) - LogRocket
- [How to Use Smart Defaults to Reduce Cognitive Load](https://www.shopify.com/partners/blog/cognitive-load) - Shopify
- [How to Use Smart Defaults to Optimize Form UX](https://www.zuko.io/blog/how-to-use-defaults-to-optimize-your-form-ux) - Zuko
- [Zero UI: Modern Use Cases](https://blog.logrocket.com/ui-design/zero-ui/) - LogRocket

**Key Findings**:
- **Progressive disclosure** (coined by Jakob Nielsen in 1995) defers advanced features to secondary UI, reducing cognitive load
- **Conditional disclosure**: Show fields only when conditions are met (e.g., "Yes" reveals additional fields)
- **Smart defaults** can improve form completion by **10-20%** and reduce typing effort
- Google research shows auto-filling helps users complete forms **30% faster**
- Only set defaults when **95%+ of users** would choose that value
- **Zero UI** uses context, intent, and behavioral patterns to anticipate user needs
- Always allow users to edit/override auto-populated values

#### 3. Auto-Import & Data Fetching

**Key Sources**:
- [Form Design Best Practices 2025](https://buildform.ai/blog/form-design-best-practices/) - Buildform
- [How Smart Defaults Reduce Form Errors](https://www.reform.app/blog/how-smart-defaults-reduce-form-errors) - Reform
- [How to Build Better UX with API](https://devico.io/blog/how-to-build-a-better-user-experience-with-api) - Devico
- [Can I Pre-populate Forms?](https://help.typeform.com/hc/en-us/articles/360039114331-Can-I-pre-populate-forms) - Typeform Help

**Key Findings**:
- Google's Places API address autocomplete can **reduce keystrokes by over 70%** and cut down on address entry errors
- APIs enable sidestepping technically demanding steps that users would prefer skipping
- Forcing users to manually input the same data across different services creates a disjointed experience
- Workflow automation reduces manual input and human error
- Best practices: Use data-driven defaults, leverage context/location, always allow user control
- Eliminate redundant information collection (e.g., don't ask for city when ZIP code can derive it)

#### 4. Responsive Tables & Mobile Data Display

**Key Sources**:
- [Responsive Data Tables](https://css-tricks.com/responsive-data-tables/) - CSS-Tricks
- [Table Design Patterns on the Web](https://www.smashingmagazine.com/2019/01/table-design-patterns-web/) - Smashing Magazine
- [Mobile Tables](https://www.nngroup.com/articles/mobile-tables/) - Nielsen Norman Group
- [Touch Target Size](https://www.nngroup.com/articles/touch-target-size/) - Nielsen Norman Group
- [One Handed Use](https://www.lukew.com/ff/entry.asp?1927) - Luke Wroblewski
- [Mobile UX](https://www.nngroup.com/articles/mobile-ux/) - Nielsen Norman Group

**Key Findings - Responsive Table Patterns**:
- **No universal best practice exists** - "it's all about the specific context of your data table" (CSS-Tricks)
- Common approaches:
  - **Horizontal scrolling with visual cues**: Maintains table structure, uses gradient shadows to signal more content
  - **Stacked/card layout**: Convert rows to vertical blocks with labels - works well for few columns
  - **Column priority/hiding**: Show only essential columns on mobile, hide less important ones
  - **Sticky headers + fixed left column**: Lock headers and labels for context during scrolling
  - **Minimal intervention**: For tables with few columns, "pretty much mobile-ready to begin with" (Smashing Magazine)

**Key Findings - Mobile Touch Interactions**:
- **Touch target minimum**: At least **1cm × 1cm (0.4in × 0.4in)** physical size (NNG)
- **Spacing requirement**: Approximately **2mm spacing** between targets to prevent accidental taps
- **Primary actions**: Should be **2cm × 2cm (0.8in × 0.8in)** or larger
- **Bottom positioning**: Place frequently-used controls at screen bottom where thumbs naturally reach (Wroblewski)
- **Functionality over aesthetics**: "Minimize crowding" - densely packed elements increase errors even when targets meet minimum size
- **Context matters**: "Items need to be legible without requiring the user to zoom in" (NNG)

**Key Findings - Mobile Complexity**:
- **Content prioritization**: "Whenever you include a new design element... something else gets pushed out" (NNG Mobile UX)
- **Simplification over density**: Break complex workflows into smaller steps rather than cramming everything
- **State management**: Save progress continuously for interrupted sessions
- **Reduce visual clutter**: Minimize chrome to maximize content space

2. **Find Real-World Examples**: Look at how successful products solve similar problems
   - SaaS platforms (GitHub, Stripe, Linear, Notion)
   - Tournament management systems (Challonge, Toornament, Start.gg)
   - Form-heavy applications (Typeform, Google Forms, JotForm)

3. **Document Findings**: Record what you learned and why it applies
   - Quote specific principles or studies
   - Include links to examples
   - Explain the reasoning behind the pattern

4. **Generalize Learnings**: Extract principles that can be applied elsewhere
   - Identify the underlying UX pattern
   - Consider where else in the application it applies
   - Add to this document for future reference

5. **Update Tests**: Ensure E2E tests reflect the new behavior
   - Update existing test assertions
   - Add new tests for changed flows
   - Remove obsolete test cases

## Improvement Patterns

### Pattern: Post-Creation Flows
**UX Principle**: Minimize clicks and friction in common workflows. Users should be seamlessly guided to the next logical step.

**Research Areas**:
- Nielsen Norman Group: "Success feedback" patterns
- Post-creation redirects vs. confirmation pages
- Progressive disclosure of important information (like access tokens)

**Examples to Study**:
- GitHub: Creating a repository redirects immediately to the repo page
- Stripe: Creating an API key shows it once with copy button, then navigates to the resource
- Linear: Creating an issue shows success toast and navigates to the issue

### Pattern: Smart Defaults & Auto-Import
**UX Principle**: Reduce cognitive load by eliminating unnecessary inputs. Pull data from existing sources when possible.

**Research Areas**:
- Progressive disclosure in form design
- Smart defaults and pre-population
- Zero-UI patterns (actions that happen without explicit user input)

**Examples to Study**:
- Shopify: Auto-detects product details from URLs when adding items
- Typeform: Pre-fills fields based on query parameters or previous responses
- Notion: Auto-suggests templates and content based on context

---

## Improvements

### #1: Auto-Forward After Tournament Creation

**Status**: ✅ Implemented
**Date Proposed**: 2026-01-16
**Priority**: High (affects every tournament creation)

#### Problem

Currently, after creating a tournament:
1. User sees a success message
2. User sees the admin token displayed
3. User sees a link to the tournament
4. User must click the link to proceed

This creates unnecessary friction. The user's intent is clear—they want to start managing the tournament they just created.

#### Research Findings

**UX Principle**: "Don't Make Me Click" / Direct Manipulation
- Users should be automatically transitioned to the next logical step in their workflow
- Confirmation pages are appropriate when the action is destructive or when users need to copy information
- For creation flows, immediate redirect with success feedback is preferred

**Evidence from Research**:
- Per [Pencil & Paper](https://www.pencilandpaper.io/articles/success-ux): "For smaller scale task completions, you might prefer opting for a more subtle success UI like a banner or toast" rather than full-page confirmations
- Per [Authgear](https://www.authgear.com/post/login-signup-ux-guide): "Requiring email confirmation before allowing users to explore your product can interrupt momentum" — same principle applies to post-creation flows
- Per [UserOnboard](https://www.useronboard.com/onboarding-ux-patterns/success-states/): Success states serve three purposes: **Confirmation** (did it work?), **Context** (what's next?), and **Celebration** (for meaningful accomplishments)

**Pattern Examples**:
- **GitHub**: Creating a repository → immediate redirect to repo page with success toast
- **Stripe**: Creating a product → redirect to product detail page with confirmation banner
- **Linear**: Creating an issue → redirect to issue view with brief success animation

**Key Insight**: Important information (like admin tokens) should be preserved and displayed in the destination, not just the confirmation page. The redirect maintains momentum while the success feedback provides confirmation.

#### Proposed Solution

1. **Immediate Redirect**: After successful creation, redirect to `/tournament/{id}` dashboard
2. **Preserve Token Display**: Show admin token prominently on the dashboard with:
   - Clear "Admin Token" label
   - Copy-to-clipboard button
   - Visual indication it's been saved to cookies
   - Dismissible (but recoverable from cookies)
3. **Success Feedback**: Show a non-intrusive success toast/banner: "Tournament created successfully"

#### Implementation Notes

**Files to Modify**:
- `src/Controllers/TournamentController.php`: Change POST `/api/tournaments` response from JSON to redirect
- `src/Views/tournament/dashboard.php`: Add admin token display section (check if token was just created)
- Session/cookie: Add flag for "just_created" to show token prominently

**Flow**:
```
User submits form → Tournament created → Set cookie with token →
Redirect to dashboard → Show token display + success message
```

**Fallback**: If JavaScript is disabled or redirect fails, fall back to current behavior with link.

#### Testing Considerations

**E2E Tests to Update**:
- `tests/E2E/specs/tournament-creation.spec.ts`: Update to expect immediate redirect instead of link click
- Add assertion for admin token display on dashboard page
- Verify success message appears
- Test copy-to-clipboard functionality (if implemented)

**New Test Cases**:
- Verify token is accessible after refresh (from cookie)
- Verify token display is dismissible but recoverable
- Test that token is not visible after cookie expiration

#### Generalized Learning

**Pattern**: Post-Creation Auto-Forward
- **When to use**: Creation flows where the user's next action is obvious
- **When not to use**: When user needs to copy critical information that won't be available later, or when the creation might be part of a batch operation
- **Key components**: Redirect + preserved information + success feedback
- **Application elsewhere**: Could apply to round creation, player creation (if added in future)

---

### #2: Remove Table Count Input, Auto-Import First Round

**Status**: ✅ Implemented
**Date Proposed**: 2026-01-16
**Priority**: High (affects every tournament creation)

#### Problem

Currently, when creating a tournament:
1. User must manually input the number of tables
2. This information is redundant—BCP pairings data already contains it
3. User then must manually navigate to import the first round
4. This creates extra steps and potential for error (wrong table count)

The table count is determined by the number of pairings in the first round, so asking for it upfront is unnecessary.

#### Research Findings

**UX Principle**: Progressive Disclosure + Smart Defaults
- Only ask for information that can't be automatically determined
- Reduce form fields to the absolute minimum
- Use API integrations to fetch data rather than asking users to input it

**Evidence from Research**:
- Per [Nielsen Norman Group](https://www.nngroup.com/articles/progressive-disclosure/): Progressive disclosure "reduces cognitive load by gradually revealing more complex information or features as the user progresses"
- Per [Zuko](https://www.zuko.io/blog/how-to-use-defaults-to-optimize-your-form-ux): "Default values can improve completion by **10-20%**, reduce typing effort, enable faster form completion"
- Per [Shopify](https://www.shopify.com/partners/blog/cognitive-load): "Smart defaults—values set based on information available about the user—can make form completion faster and more accurate"
- Google's research shows auto-filling helps people fill out forms **30% faster** ([Buildform](https://buildform.ai/blog/form-design-best-practices/))
- Per [Reform](https://www.reform.app/blog/how-smart-defaults-reduce-form-errors): "Features of minimalist form design include... the elimination of redundant information collection (e.g., asking for both city and zip code when one can be derived from the other)"

**Pattern Examples**:
- **Shopify**: When adding a product from a URL, automatically fetches product details, images, pricing
- **Stripe**: When setting up payment methods, auto-detects card type, validates format in real-time
- **Typeform**: Uses [hidden fields and URL parameters](https://help.typeform.com/hc/en-us/articles/360039114331-Can-I-pre-populate-forms) to pre-populate form data
- **Calendly**: Pulls availability from connected calendars rather than asking users to input it

**Key Insight**: "The best form field is no form field"—every input removed reduces cognitive load and potential errors.

#### Research: Auto-Import Patterns

**Zero-UI / Automatic Actions**:
- Actions that happen without explicit user request
- Requires clear user intent and low risk of error
- Should have undo/edit capability

**Evidence from Research**:
- Per [LogRocket](https://blog.logrocket.com/ui-design/zero-ui/): Zero UI "uses context, intent, and behavioral patterns to anticipate user needs" and can "automatically provide relevant contextual information" without explicit commands
- Per [Devico](https://devico.io/blog/how-to-build-a-better-user-experience-with-api): "Forcing users to jump between platforms or manually input the same data across different services can create a disjointed experience"
- Per [Integrate.io](https://www.integrate.io/blog/ultimate-guide-to-api-integration-solutions/): "Workflow automation reduces manual input and human error by streamlining cross-system workflows"

**Examples**:
- **Notion**: Auto-saves documents continuously
- **Gmail**: Auto-categorizes emails into Primary/Social/Promotions
- **Slack**: Auto-links @mentions and #channels as you type

#### Proposed Solution

**Option A: Fully Automatic (Recommended)**
1. Remove "Number of Tables" field from tournament creation form
2. After tournament is created, automatically attempt to import Round 1 from BCP
3. Derive table count from the number of pairings (n pairings = n tables)
4. Create tables automatically (Table 1, Table 2, ..., Table n)
5. Show success message: "Tournament created with {n} tables (from Round 1 pairings)"

**Option B: Semi-Automatic (Fallback)**
1. Remove "Number of Tables" field
2. After creation, show dashboard with prominent "Import Round 1" button
3. Make this button the primary action (large, visually prominent)
4. Once imported, derive and create tables automatically
5. Until Round 1 is imported, show placeholder: "Tables will be created after importing Round 1"

**Recommendation**: Start with Option A. If auto-import fails (BCP API down, invalid URL, etc.), fall back to showing an error with manual import option.

#### Implementation Notes

**Files to Modify**:
- `src/Views/tournament/create.php`: Remove table count input field
- `src/Controllers/TournamentController.php`:
  - Remove table_count validation
  - After tournament creation, trigger auto-import of Round 1
  - Derive table count from imported pairings
- `src/Services/BCPScraperService.php`: Ensure robust error handling
- `src/Controllers/RoundController.php`:
  - Modify import logic to support "first import" scenario
  - Create tables if they don't exist (current logic might assume tables exist)

**Flow**:
```
User submits form (name, BCP URL) → Tournament created →
Attempt auto-import Round 1 →
  Success: Create tables from pairing count → Redirect to dashboard with success
  Failure: Redirect to dashboard with error, show manual import button
```

**Edge Cases**:
- BCP API is down → Show error, offer manual import
- BCP URL is invalid → Show error on creation form (validate before creating tournament)
- Round 1 has no pairings yet → Show message "Round 1 not yet published on BCP"
- Odd number of players (bye round) → Handle correctly (n pairings = n tables, bye player not assigned)

**Migration Considerations**:
- Existing tournaments: No change needed (they already have tables)
- Database: No schema change needed (table_count was never stored, derived from count)

#### Testing Considerations

**E2E Tests to Update**:
- `tests/E2E/specs/tournament-creation.spec.ts`:
  - Remove table count input interaction
  - Verify tables are created automatically
  - Verify Round 1 is imported automatically
  - Test both success and failure scenarios

**New Test Cases**:
- Test auto-import failure handling (mock BCP API failure)
- Verify error messages are clear and actionable
- Test manual import fallback if auto-import fails
- Verify table count matches pairing count
- Test with different pairing counts (4, 8, 16, odd numbers with byes)

**Unit Tests**:
- Test table creation logic when count is derived from pairings
- Test error handling in BCPScraperService

#### Generalized Learning

**Pattern**: Auto-Import with Smart Defaults
- **When to use**: When data can be reliably fetched from an external source
- **When not to use**: When the external source is unreliable or when user might want to override
- **Key components**: Automatic action + clear success/error feedback + manual fallback
- **Requirements**: Robust error handling, clear user communication, undo/edit capability
- **Application elsewhere**:
  - Could auto-import subsequent rounds when user navigates to them
  - Could auto-fetch player details from BCP
  - Could auto-suggest terrain types based on tournament size

**Pattern**: Progressive Disclosure in Forms
- **When to use**: When information can be derived or is conditionally required
- **Key principle**: Every form field you remove reduces cognitive load and increases completion rate
- **Application elsewhere**:
  - Player creation (if added): Auto-fetch from BCP instead of manual entry
  - Round creation: Auto-detect next round number
  - Terrain type assignment: Suggest based on existing tables or provide defaults

---

### #3: Compact & Responsive Allocation Table

**Status**: ✅ Implemented
**Date Proposed**: 2026-01-19
**Date Implemented**: 2026-01-19
**Priority**: High (affects every round management interaction)

#### Problem

The current allocation table (`src/Views/round/manage.php`) has several UX issues:

1. **Not mobile-responsive**: No responsive breakpoints, requires horizontal scrolling on mobile
2. **Too many columns (9)**: Select, Table, Terrain, Player 1, Score, Player 2, Score, Status, Change Table
3. **Redundant information**: Score column separate from player names; Status column duplicates conflict highlighting
4. **Wasted space**: Terrain shows "-" for undefined values; Status shows "✓ OK" for most rows
5. **Not touch-friendly**: Dropdowns and checkboxes too small for mobile; no consideration for thumb reach
6. **Horizontal scrolling required**: Table doesn't adapt to small screens

Current table width requirements make it unusable on phones without pinch-zoom-scroll, creating poor UX for admins managing tournaments on mobile devices.

#### Research Findings

**UX Principle**: Responsive Tables & Mobile-First Design

Based on research from Nielsen Norman Group, CSS-Tricks, and Smashing Magazine, the key principles for mobile tables are:

1. **Context-specific solutions**: "It's all about the specific context of your data table" - no universal pattern fits all
2. **Column reduction**: Show only essential information on mobile, hide secondary details
3. **Compact representation**: Combine related data (e.g., player + score) to reduce columns
4. **Touch-friendly targets**: Minimum 1cm × 1cm (0.4in × 0.4in) physical size for interactive elements
5. **Progressive disclosure**: Move less-frequent actions (swap, edit) to secondary UI on mobile
6. **Sticky headers + fixed columns**: Maintain context during scrolling
7. **Visual indicators over text**: Use color/icons instead of text labels where possible

**Evidence from Research**:

**Responsive Table Patterns**:
- Per [CSS-Tricks](https://css-tricks.com/responsive-data-tables/): No universal best practice - choose based on data type and density
- Per [Smashing Magazine](https://www.smashingmagazine.com/2019/01/table-design-patterns-web/): "For tables with few columns and many rows... pretty much mobile-ready to begin with" with minimal intervention
- Per [Nielsen Norman Group](https://www.nngroup.com/articles/mobile-tables/): "Items need to be legible without requiring the user to zoom in" and use fixed left columns for context

**Touch Interaction Guidelines**:
- Per [NNG Touch Targets](https://www.nngroup.com/articles/touch-target-size/): Minimum **1cm × 1cm (0.4in × 0.4in)** touch targets with **~2mm spacing**
- Per [Luke Wroblewski](https://www.lukew.com/ff/entry.asp?1927): Place frequent actions at bottom of screen where thumbs naturally reach
- Per [NNG Mobile UX](https://www.nngroup.com/articles/mobile-ux/): "Whenever you include a new design element... something else gets pushed out" - prioritize ruthlessly

**Pattern Examples**:
- **Linear**: Mobile issue lists show compact rows with inline badges, tap-to-expand for actions
- **Trello**: Card lists use vertical stacking, swipe gestures for actions (move, delete)
- **Notion**: Tables adapt to card view on mobile, showing only critical fields
- **GitHub**: PR lists on mobile show status icons, abbreviated text, tap for details

**Key Insight**: Combine data into fewer columns (player + score), remove redundant information (status text), and use progressive disclosure for actions (edit/swap behind tap/swipe).

#### Proposed Solution

**Option A: Hybrid Responsive Table (Recommended)**

Maintain table structure on desktop, adapt for mobile with CSS and progressive disclosure.

**Desktop (≥768px) - 6 columns** (reduced from 9):
1. **Select** (40px) - Checkbox for swapping
2. **Table** (~80px) - "Table 3" or "Table 3 (Forest)" if terrain assigned
3. **Player 1** (~35%) - "Tamas Horvath (4)" - score in muted style
4. **vs** (~20px) - Visual separator "vs"
5. **Player 2** (~35%) - "Istvan Madarasz (3)" - score in muted style
6. **Change** (~120px) - Dropdown to reassign table

**Mobile (<768px) - 4-5 columns**:
1. **Select** (44px) - Larger checkbox target (1cm minimum)
2. **Table** (~60px) - "T3" or "T3 🌲" - abbreviated + emoji for terrain
3. **Matchup** (flexible) - "T. Horvath (4) vs I. Madarasz (3)" - abbreviated names
4. **Edit** (44px) - Icon button (pencil) opens modal for table change

**Changes**:
- ✅ Remove separate Score columns - display next to names: "Player Name (4)"
- ✅ Remove Status column - conflicts shown via row highlight + summary above table
- ✅ Merge terrain into Table column - "Table 3 (Forest)" or icon on mobile
- ✅ Hide "-" for undefined terrain
- ✅ Abbreviate headers on mobile: "Select" → hidden label (icon only), "Player 1" → "P1"
- ✅ Abbreviate names on mobile: "Tamas Horvath" → "T. Horvath" (keep first name + initial)
- ✅ Increase touch targets to 44×44px minimum on mobile
- ✅ Simplify change table interaction on mobile (modal instead of dropdown)

**Option B: Card/List View for Mobile**

Switch to card-based layout below 768px, maintain table above.

**Desktop**: Same as Option A
**Mobile**: Each allocation becomes a card:
```
┌─────────────────────────────┐
│ ☐ Table 3 🌲                │
│ T. Horvath (4) vs           │
│ I. Madarasz (3)             │
│ [Edit Table] [Swap]         │
└─────────────────────────────┘
```

**Recommendation**: Start with Option A. Card view (Option B) is a more radical change and may disorient users who expect tables.

#### Mobile-Friendly Edit & Swap Interactions

**Current Issues**:
- Dropdown select too small (requires precise tap)
- Checkbox selection awkward with small targets
- Swap button at bottom requires scrolling on long lists

**Proposed Solutions for Table Editing**:

1. **Modal Overlay (Recommended for Mobile)**
   - Tap pencil icon → opens bottom sheet/modal
   - Large touch targets for each table option
   - Preview shows: "Move T. Horvath vs I. Madarasz to Table 5 (Desert)"
   - Dismiss or confirm
   - **Pros**: Large targets, clear preview, standard mobile pattern
   - **Cons**: Extra step (tap to open)

2. **Inline Dropdown with Larger Targets**
   - Increase dropdown height on mobile (44px minimum)
   - Use native `<select>` which triggers OS picker on mobile
   - **Pros**: Fewer steps, familiar pattern
   - **Cons**: Still requires precise tap to open

3. **Swipe to Edit (Advanced)**
   - Swipe row left → reveals "Change Table" action
   - Tap → opens modal with table options
   - **Pros**: Gesture-based, saves space
   - **Cons**: Not discoverable, requires tutorial

**Proposed Solutions for Swapping**:

1. **Sticky Action Bar (Recommended)**
   - Float swap button at bottom of screen (sticky/fixed)
   - Shows count: "Swap 2 selected" or "Select 2 to swap" when disabled
   - Always accessible, no scrolling needed
   - **Pros**: Always reachable (thumb zone), clear feedback
   - **Cons**: Occludes content slightly

2. **Batch Action Sheet**
   - Checkbox selection enters "selection mode"
   - Action bar appears at bottom with: "Swap (2 selected)" + "Cancel"
   - Similar to iOS Mail or Photos selection pattern
   - **Pros**: Standard mobile pattern, clear mode
   - **Cons**: Requires entering/exiting selection mode

3. **Drag & Drop (Advanced)**
   - Long-press row → enters drag mode
   - Drag to another row → swaps on drop
   - Visual feedback during drag
   - **Pros**: Direct manipulation, intuitive
   - **Cons**: Hard to implement with table structure, requires significant JS

4. **Swipe to Swap (Alternative)**
   - Swipe row right → reveals "Swap" action
   - Tap "Swap" → enters selection mode, tap another row → confirms
   - **Pros**: Gesture-based, efficient
   - **Cons**: Two-step process, not discoverable

**Recommendations**:
- **Edit interaction**: Modal overlay on mobile (≤768px), dropdown on desktop
- **Swap interaction**: Sticky action bar (always visible at bottom) on all screen sizes

#### Implementation Notes

**Files to Modify**:
- `src/Views/round/manage.php`:
  - Reduce table columns from 9 to 6
  - Combine player name + score: `<?= $player->name ?> <span class="score">(<?= $allocation->player1Score ?>)</span>`
  - Combine table + terrain: `Table <?= $table->tableNumber ?><?php if ($terrain): ?> (<?= $terrain->name ?>)<?php endif; ?>`
  - Remove Status column (conflicts already highlighted via row classes)
  - Add responsive CSS with breakpoints
  - Add modal markup for mobile table editing
  - Make swap button sticky on mobile

**Responsive CSS Strategy**:
```css
/* Base (mobile-first) */
.allocation-table { font-size: 14px; }
.allocation-table th { padding: 8px 4px; }
.allocation-table td { padding: 8px 4px; }
.select-checkbox { width: 44px; height: 44px; } /* touch-friendly */
.change-table-btn { display: block; } /* modal trigger */
.change-table-dropdown { display: none; } /* hide on mobile */
.swap-button { position: sticky; bottom: 20px; } /* always accessible */

/* Abbreviations on mobile */
.player-name { /* JS truncates to "FirstName L." */ }
.table-header-full { display: none; }
.table-header-short { display: inline; } /* "T#" instead "Table" */

/* Desktop (≥768px) */
@media (min-width: 768px) {
  .allocation-table { font-size: 16px; }
  .allocation-table th { padding: 12px 8px; }
  .allocation-table td { padding: 12px 8px; }
  .select-checkbox { width: 20px; height: 20px; }
  .change-table-btn { display: none; }
  .change-table-dropdown { display: block; }
  .swap-button { position: static; } /* return to flow */
  .table-header-full { display: inline; }
  .table-header-short { display: none; }
}
```

**JavaScript Changes**:
- Add `openTableChangeModal(allocationId)` function for mobile
- Modify `changeTableAssignment()` to work from modal selections
- Add name abbreviation function: `abbreviateName(fullName)` returns "FirstName L."
- Update `updateSwapButton()` to handle sticky positioning

**Modal Structure** (for mobile table editing):
```html
<div id="change-table-modal" class="modal" style="display: none;">
  <div class="modal-backdrop"></div>
  <div class="modal-content">
    <h3>Change Table Assignment</h3>
    <p class="modal-description">Player 1 vs Player 2</p>
    <div class="table-options">
      <!-- Large touch-friendly buttons for each table -->
      <button onclick="changeTableAssignment(allocId, tableId)">
        Table 1 (Forest)
      </button>
      <!-- ... -->
    </div>
    <button class="modal-cancel">Cancel</button>
  </div>
</div>
```

**Edge Cases**:
- Very long player names → CSS `text-overflow: ellipsis` on mobile
- Undefined terrain → Hide text, don't show "-"
- Large tournament (20+ tables) → Modal becomes scrollable
- Sticky button on short lists → Only sticky if content height > viewport

#### Testing Considerations

**Responsive Testing**:
- Test at breakpoints: 320px (iPhone SE), 375px (iPhone 12), 768px (iPad), 1024px (desktop)
- Verify no horizontal scrolling on mobile
- Check touch target sizes (use browser dev tools overlay)
- Test with long player names and many tables

**E2E Tests to Update**:
- `tests/E2E/specs/round-management.spec.ts` (or create if doesn't exist):
  - Verify table displays correctly on desktop (6 columns)
  - Verify table editing via dropdown works on desktop
  - Verify swap functionality with sticky button
  - Add mobile viewport tests (320px, 375px)
  - Verify modal opens on mobile edit
  - Verify name abbreviation on mobile
  - Verify no horizontal scroll on mobile

**Manual Testing**:
- Test on real devices (iPhone, Android phone, iPad)
- Verify sticky button doesn't block content
- Check Pico CSS compatibility (no style conflicts)
- Test with various tournament sizes (4, 8, 16 tables)

**Accessibility Testing**:
- Ensure abbreviations have `title` or `aria-label` with full text
- Verify modal is keyboard-navigable and screen-reader friendly
- Check color contrast for score text (muted style must meet WCAG AA)
- Test with browser zoom (150%, 200%)

#### Generalized Learning

**Pattern**: Responsive Data Tables with Progressive Disclosure
- **When to use**: Complex tables with multiple columns that need to work on mobile
- **Key principles**:
  - Combine related data to reduce columns (player + score)
  - Remove redundant information (status text when row highlight exists)
  - Use progressive disclosure for actions (modals on mobile, inline on desktop)
  - Sticky action buttons for batch operations (always in thumb reach)
  - Mobile-first CSS with breakpoints
  - Touch targets minimum 44×44px on mobile
- **Application elsewhere**:
  - Tournament dashboard table (could benefit from similar responsive treatment)
  - Player lists (if added in future)
  - Any admin interface with tabular data

**Pattern**: Touch-Friendly Action Patterns
- **Modal overlays**: Best for infrequent, complex actions (table editing) on mobile
- **Sticky action bars**: Best for batch operations (swap, delete) that need constant access
- **Native controls**: Use native `<select>` on mobile - triggers OS picker with large targets
- **Bottom positioning**: Place frequent actions at bottom (thumb zone) on mobile

---

### Candidate Improvements (Not Yet Researched)

1. **Inline Editing**: Edit allocations directly in the table view without modal/separate page
2. **Undo/Redo**: Allow undoing manual allocation changes
3. **Keyboard Shortcuts**: Power user features for quick navigation and actions
4. **Bulk Operations**: Select multiple allocations for batch editing
5. **Visual Table Map**: Show physical layout of tables with allocations
6. **Conflict Highlighting**: Visual indicators for allocation conflicts (same player, double-booked table)
7. **Drag-and-Drop**: Drag players between tables to reassign
8. **Real-time Collaboration**: Multiple admins can edit simultaneously with conflict resolution
9. **Mobile Optimization**: Improve touch targets and layout for tablet/phone admin interface
10. **Accessibility**: Ensure screen reader compatibility, keyboard navigation, WCAG 2.1 AA compliance

---

## Research Resources

### General UX Resources
- [Nielsen Norman Group](https://www.nngroup.com/) - UX research and guidelines
- [Baymard Institute](https://baymard.com/) - E-commerce and form UX research
- [Material Design](https://material.io/) - Google's design system
- [Apple Human Interface Guidelines](https://developer.apple.com/design/human-interface-guidelines/)
- [Smashing Magazine](https://www.smashingmagazine.com/) - Web design articles and case studies

### Form Design Specific
- [Web Form Design: Filling in the Blanks](https://www.lukew.com/ff/) by Luke Wroblewski
- [UX Movement](https://uxmovement.com/) - Form design patterns
- [Form Design Patterns](https://www.smashingmagazine.com/printed-books/form-design-patterns/) by Adam Silver

### Tournament/Event Management Examples
- [Best Coast Pairings](https://www.bestcoastpairings.com/) - Wargaming tournament management
- [Challonge](https://challonge.com/) - Tournament bracket management
- [Start.gg](https://www.start.gg/) (formerly Smash.gg) - Esports tournament platform
- [Toornament](https://www.toornament.com/) - Tournament organization platform

---

## Document Maintenance

### When to Update This Document

- **Before implementing**: Research and document new improvements here first
- **After implementing**: Update status, add "Implemented" date, record lessons learned
- **During code review**: If reviewer suggests UX improvements, document them here
- **Retrospectives**: Review this document and add new learnings from completed work

### Document Structure for New Improvements

```markdown
### #N: Improvement Title

**Status**: ⏳ Proposed / 🚧 In Progress / ✅ Implemented / ❌ Rejected
**Date Proposed**: YYYY-MM-DD
**Date Implemented**: YYYY-MM-DD (if applicable)
**Priority**: High / Medium / Low

#### Problem
What is the current UX issue? Why is it a problem?

#### Research Findings
What UX principles apply? What did you learn from other products?

#### Proposed Solution
What is the recommended approach? Include alternatives if applicable.

#### Implementation Notes
Technical details, files to change, flow diagrams, edge cases.

#### Testing Considerations
What tests need to be updated? What new tests are needed?

#### Generalized Learning
What pattern can be extracted? Where else could this apply?
```

---

## Changelog

- **2026-01-19**: Implemented improvement #3 (Compact & Responsive Allocation Table): reduced columns from 9 to 6, added mobile breakpoints, sticky swap controls, modal for mobile table editing
- **2026-01-19**: Added improvement #3 (Compact & Responsive Allocation Table) with mobile-first design research and interaction patterns
- **2026-01-19**: Added research section #4 (Responsive Tables & Mobile Data Display) with sources from NNG, CSS-Tricks, Smashing Magazine, Luke Wroblewski
- **2026-01-16**: Completed pending research queries with web searches; added evidence and source links to improvement sections
- **2026-01-16**: Document created with first two improvements (post-creation redirect, auto-import)
