# MDI (Multiple Document Interface) Implementation - EC EMR

## Overview
The EC EMR now features a **browser-like tabbed interface** that allows users to open multiple patient charts, visits, and other pages simultaneously. All navigation has been converted to open in tabs instead of full-page navigation.

## Key Components

### 1. **MDI Shell** (`mdi_shell.php`)
- Main entry point after login
- Contains the tab bar, toolbar, and iframe content area
- All users are redirected here after successful login

### 2. **MDI Manager** (`js/mdi_manager.js`)
- Core tab management logic (open, close, switch tabs)
- Persistent tab state using `localStorage`
- Keyboard shortcuts:
  - **Ctrl+T**: Open new dashboard tab
  - **Ctrl+W**: Close current tab
  - **Ctrl+Tab**: Switch to next tab
  - **Ctrl+Shift+Tab**: Switch to previous tab
  - **Middle-click**: Close tab

### 3. **MDI Navigation** (`js/mdi_navigation.js`)
- Automatically intercepts all link clicks in embedded pages
- Converts regular navigation to tab-based navigation
- Automatically loaded when `?layout=modal` is present

### 4. **Header Detection** (`templates/header.php`)
- Detects `?layout=modal` parameter
- Hides sidebar and applies minimal styling for embedded pages
- Loads `mdi_navigation.js` in modal mode

## Usage

### Opening Pages in Tabs (JavaScript)
```javascript
// Method 1: Using global helper
window.openPageInTab('patient_profile.php?id=123', 'Patient Profile', 'user');

// Method 2: From within iframe
window.parent.openPageInTab('visit_vitals.php?patient_id=123', 'Vitals', 'activity');

// Method 3: Using navigateInTab (auto-detect)
window.navigateInTab('appointments_calendar.php');
```

### Link Attributes
```html
<!-- Normal link - will open in tab automatically -->
<a href="patient_profile.php?id=123">View Profile</a>

<!-- Custom tab title and icon -->
<a href="patient_profile.php?id=123" data-tab-title="John Doe" data-tab-icon="user">View Profile</a>

<!-- Prevent tab navigation (use regular navigation) -->
<a href="external_page.php" data-no-mdi>Regular Link</a>
```

### Pinned Tabs
- Dashboard tab can be pinned (cannot be closed)
- Set `isPinned: true` when calling `openTab()`

## Modified Files

### Core Files
- `mdi_shell.php` - New MDI container
- `js/mdi_manager.js` - Tab management logic
- `js/mdi_navigation.js` - Navigation interception
- `templates/header.php` - MDI mode detection
- `login.php` - Redirect to MDI shell
- `index.php` - Redirect logged-in users to MDI shell

### Configuration
- All pages now support `?layout=modal` parameter for embedded rendering
- Sidebar is automatically hidden in modal mode
- Tab state is persisted in `localStorage` under key `ec_mdi_tabs`

## Features

### Tab Management
- Maximum 10 tabs (configurable in `mdi_manager.js`)
- Duplicate URLs automatically switch to existing tab
- Tabs persist across page refreshes
- Visual indicators for active tab

### User Experience
- Smooth tab switching with no page reloads
- Loading indicator during iframe content load
- Toast notifications for errors (e.g., max tabs reached)
- Responsive design for mobile/tablet

### Security
- iframe sandbox attributes for security
- Session validation still enforced per page
- No cross-origin issues (all same-origin)

## Keyboard Shortcuts
| Shortcut | Action |
|----------|--------|
| `Ctrl+T` | New dashboard tab |
| `Ctrl+W` | Close current tab |
| `Ctrl+Tab` | Next tab |
| `Ctrl+Shift+Tab` | Previous tab |
| `Middle-click` | Close tab |

## Troubleshooting

### Tabs not persisting
- Check browser localStorage is enabled
- Verify `ec_mdi_tabs` key exists in localStorage

### Links not opening in tabs
- Verify `mdi_navigation.js` is loaded
- Check for `data-no-mdi` attribute on links
- Ensure URL doesn't start with `http://`, `https://`, `javascript:`, `mailto:`, or `tel:`

### Iframe content not loading
- Check that target page exists
- Verify session is active
- Check browser console for errors
- Ensure page supports `?layout=modal` parameter

## Future Enhancements
- Drag-and-drop tab reordering
- Tab groups/workspaces
- Split-screen view (2 tabs side-by-side)
- Recently closed tabs history
- Export/import tab sessions
