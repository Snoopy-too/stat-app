# Button Styles Guide

This guide documents all button styles and patterns used throughout the StatApp application for consistency.

## Button Classes

### Primary Buttons (`.btn`)
Used for primary actions like Save, Submit, Register, Create.

```html
<button type="submit" class="btn">Save Changes</button>
<a href="/path" class="btn">Go to Page</a>
```

**Usage:** Main call-to-action, form submissions, primary navigation actions
**Color:** Blue (#4c7ad9)
**Min Height:** 44px

---

### Secondary Buttons (`.btn--secondary`)
Used for secondary actions like navigation back, edit, view options.

```html
<a href="dashboard.php" class="btn btn--secondary">Back to Dashboard</a>
<button class="btn btn--secondary">Edit</button>
```

**Usage:** Navigation, secondary actions, alternative options
**Color:** Light gray with dark text
**Min Height:** 44px

---

### Subtle Buttons (`.btn--subtle`)
Used for tertiary actions like Cancel, Reset, or action buttons in tables.

```html
<a href="/" class="btn btn--subtle">Cancel</a>
<button type="button" class="btn btn--subtle">Apply Filter</button>
```

**Usage:** Cancel buttons, secondary filters, less important actions
**Color:** Light blue background
**Min Height:** 44px

---

### Ghost Buttons (`.btn--ghost`)
Used for light background, links that look like buttons.

```html
<a href="/" class="btn btn--ghost">Reset Search</a>
```

**Usage:** Light/minimal actions, secondary navigation
**Color:** Blue text, no background
**Min Height:** 44px

---

### Danger Buttons (`.btn--danger`)
Used for destructive actions like Delete, Remove.

```html
<a href="delete.php?id=123" class="btn btn--danger">Delete Member</a>
```

**Usage:** Destructive actions (must be combined with confirmation dialogs)
**Color:** Red (#e74c3c)
**Min Height:** 44px

---

### Success Buttons (`.btn--success`)
Used for positive/successful completion actions.

```html
<button class="btn btn--success">Confirm Purchase</button>
```

**Usage:** Confirmation of positive actions
**Color:** Green (#2ecc71)
**Min Height:** 44px

---

## Size Variants

### Large (`.btn--large`)
```html
<button class="btn btn--large">Large Button</button>
```
**Height:** 48px

### Regular (default)
```html
<button class="btn">Regular Button</button>
```
**Height:** 44px

### Small (`.btn--small`)
```html
<a href="#" class="btn btn--small">Small Button</a>
```
**Height:** 40px

### Extra Small (`.btn--xsmall`)
```html
<button class="btn btn--xsmall">Tiny</button>
```
**Height:** 36px

---

## Button Modifiers

### Full Width (`.btn--block`)
```html
<button class="btn btn--block">Full Width Button</button>
```
Stretches to 100% width of container.

### Pill Style (`.btn--pill`)
```html
<button class="btn btn--pill">Pill Shaped</button>
```
Creates rounded pill-shaped buttons.

---

## Button Groups & Layouts

### Button Row (`.btn-row`)
Horizontal layout for multiple buttons.

```html
<div class="btn-row">
    <button class="btn">Save</button>
    <a href="#" class="btn btn--secondary">Cancel</a>
</div>
```

### Button Row - Centered (`.btn-row--center`)
```html
<div class="btn-row btn-row--center">
    <button class="btn">Centered Button</button>
</div>
```

### Button Row - Right Aligned (`.btn-row--end`)
```html
<div class="btn-row btn-row--end">
    <a href="#" class="btn btn--secondary">Cancel</a>
    <button class="btn">Save</button>
</div>
```

### Button Row - Vertical (`.btn-row--vertical`)
```html
<div class="btn-row btn-row--vertical">
    <button class="btn btn--block">Button 1</button>
    <button class="btn btn--secondary btn--block">Button 2</button>
</div>
```

### Form Actions (`.form-actions`)
Standard layout for form submission buttons.

```html
<div class="form-actions">
    <button type="submit" class="btn">Save</button>
    <a href="#" class="btn btn--subtle">Cancel</a>
</div>
```

---

## Common Patterns

### Save/Cancel Pattern
```html
<div class="form-actions">
    <button type="submit" class="btn">Save Changes</button>
    <a href="/previous-page" class="btn btn--subtle">Cancel</a>
</div>
```

### Delete with Confirmation
```html
<a href="delete.php?id=<?php echo $id; ?>"
   class="btn btn--danger"
   data-confirm="Are you sure you want to delete this?"
   data-item-name="Item Name">
    Delete
</a>
```

### Action Buttons in Tables
```html
<div class="btn-group">
    <a href="edit.php?id=123" class="btn btn--subtle btn--xsmall btn--pill">Edit</a>
    <a href="view.php?id=123" class="btn btn--ghost btn--xsmall btn--pill">View</a>
    <a href="delete.php?id=123" class="btn btn--danger btn--xsmall btn--pill"
       data-confirm="Delete this item?">Delete</a>
</div>
```

### Navigation Buttons
```html
<div class="btn-row">
    <a href="dashboard.php" class="btn btn--secondary btn--small">Back to Dashboard</a>
</div>
```

---

## States

### Disabled State
```html
<button class="btn" disabled>Disabled Button</button>
```
Automatically styled with reduced opacity.

### Loading State
```html
<button class="btn is-loading">Saving...</button>
```
Shows spinning animation. Added automatically by form-loading.js

### Focus State
All buttons have a visible focus ring (3px blue outline) for keyboard navigation.

---

## Mobile Responsive Behavior

- All buttons maintain **minimum 44px height** for easy tapping
- Button groups stack vertically on screens < 768px
- Full-width buttons can be achieved with `.btn--block` class
- Text is never truncated; buttons grow as needed

---

## Accessibility

- All buttons have proper focus states (visible outline)
- Buttons using data-confirm will trigger confirmations (with fallback to browser confirm)
- Loading states prevent multiple submissions
- Color is not the only indicator (uses text + icons when needed)

---

## Examples by Page Type

### Forms
- **Submit:** `.btn` (blue)
- **Cancel:** `.btn--subtle` (light)

### Lists/Tables
- **Edit:** `.btn btn--subtle btn--xsmall btn--pill`
- **Delete:** `.btn btn--danger btn--xsmall btn--pill`
- **View:** `.btn btn--ghost btn--xsmall btn--pill`

### Navigation
- **Back:** `.btn btn--secondary`
- **Next:** `.btn`

### Admin Dashboard
- **Primary Actions:** `.btn`
- **Secondary Navigation:** `.btn btn--secondary btn--small`

---

## Best Practices

1. **Primary Action First:** Use `.btn` (blue) for the main action
2. **Secondary Actions Second:** Use `.btn--secondary` for less important actions
3. **Destructive Last:** Place delete/danger buttons last
4. **Consistent Size:** Use `.btn--small` for action buttons in tables
5. **Confirm Destructive:** Always add `data-confirm` to delete buttons
6. **Touch Friendly:** Never make buttons smaller than `.btn--xsmall`
7. **Clear Labels:** Use action-oriented text (Save, Delete, Close, not OK, Yes, No)

---

## Related Files

- CSS: `css/components/buttons.css`
- CSS: `css/components/utilities.css` (for .btn-row classes)
- JavaScript: `js/confirmations.js` (for confirmation dialogs)
- JavaScript: `js/form-loading.js` (for loading states)
