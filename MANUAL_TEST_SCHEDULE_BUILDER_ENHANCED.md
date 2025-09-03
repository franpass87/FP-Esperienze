# Manual Test: Enhanced Schedule Builder Interface

## Test Objective
Verify that the enhanced schedule builder interface functions correctly with no errors, improved UX, and complete accessibility.

## Prerequisites
- WordPress admin access
- Experience product type enabled
- JavaScript console open for error monitoring

## Test Cases

### 1. Basic Functionality Tests

#### 1.1 Add Time Slot
**Steps:**
1. Navigate to Experience product edit page
2. Scroll to schedule builder section
3. Click "Add Time Slot" button
4. Verify button shows loading state briefly
5. Verify new time slot card appears with smooth animation
6. Verify focus automatically moves to time input field
7. Verify card scrolls into view if needed

**Expected Result:**
- ✅ Button temporarily disabled during operation
- ✅ Smooth slide-in animation (translateY + opacity)
- ✅ Focus automatically set to time input
- ✅ No JavaScript console errors
- ✅ Accessibility attributes properly set

#### 1.2 Remove Time Slot
**Steps:**
1. Add at least one time slot
2. Click "Remove" button on any time slot
3. Confirm removal in dialog
4. Verify smooth removal animation
5. If removing last slot, verify empty state appears

**Expected Result:**
- ✅ Confirmation dialog appears
- ✅ Smooth height collapse animation
- ✅ Empty state message appears if no slots remain
- ✅ Button text updates correctly

### 2. Visual Design Tests

#### 2.1 Button States
**Steps:**
1. Observe "Add Time Slot" button in various states:
   - Default state
   - Hover state
   - Focus state (tab navigation)
   - Active state (click)
   - Loading state

**Expected Result:**
- ✅ Gradient background with smooth transitions
- ✅ Proper hover effects (transform + shadow)
- ✅ Focus outline visible and accessible
- ✅ Loading spinner appears during operations
- ✅ All animations use cubic-bezier timing

#### 2.2 Card Design
**Steps:**
1. Add multiple time slots
2. Hover over cards
3. Verify gradient top border animation
4. Check card shadows and spacing

**Expected Result:**
- ✅ Cards have rounded corners and subtle shadows
- ✅ Hover effects show gradient top border
- ✅ Smooth transform animations on hover
- ✅ Consistent spacing between cards

### 3. User Experience Tests

#### 3.1 Error Handling
**Steps:**
1. Try to submit without selecting any days
2. Try to submit without setting time
3. Verify error messages and field highlighting

**Expected Result:**
- ✅ Validation errors shown clearly
- ✅ Error fields highlighted with red border
- ✅ Helpful error messages provided
- ✅ Focus moved to first error field

#### 3.2 Progressive Enhancement
**Steps:**
1. Add first time slot - button should say "Add Time Slot"
2. Add second time slot - button should say "Add Another Time Slot" 
3. Remove all slots - button should revert to "Add Time Slot"

**Expected Result:**
- ✅ Button text updates contextually
- ✅ Visual feedback matches current state
- ✅ Empty state messaging appropriate

### 4. Accessibility Tests

#### 4.1 Keyboard Navigation
**Steps:**
1. Use Tab key to navigate through interface
2. Test Enter/Space on day selection pills
3. Verify all interactive elements are focusable
4. Test screen reader announcements

**Expected Result:**
- ✅ Tab order is logical and predictable
- ✅ All buttons and inputs receive focus
- ✅ Day pills respond to keyboard activation
- ✅ Focus indicators clearly visible
- ✅ ARIA labels and roles properly set

#### 4.2 Screen Reader Support
**Steps:**
1. Use screen reader to navigate interface
2. Verify announcements for state changes
3. Check ARIA labels and descriptions

**Expected Result:**
- ✅ Time slots region properly labeled
- ✅ Day selection changes announced
- ✅ Add/remove actions clearly described
- ✅ Form validation errors read aloud

### 5. Performance Tests

#### 5.1 Animation Performance
**Steps:**
1. Add/remove multiple time slots rapidly
2. Monitor for frame drops or jank
3. Test on slower devices if possible

**Expected Result:**
- ✅ Smooth 60fps animations
- ✅ No visual glitches or jumping
- ✅ Transforms preferred over layout changes
- ✅ Reduced motion respected where specified

#### 5.2 Code Quality
**Steps:**
1. Check browser console for errors
2. Verify no deprecated jQuery methods used
3. Test event handler cleanup

**Expected Result:**
- ✅ Zero JavaScript errors in console
- ✅ No memory leaks from event handlers
- ✅ Clean namespace usage (fp-clean)
- ✅ Proper error boundaries with try-catch

## Cross-Browser Testing

### Required Browsers:
- Chrome (latest)
- Firefox (latest) 
- Safari (latest)
- Edge (latest)

### Mobile Testing:
- iOS Safari
- Android Chrome

## Performance Benchmarks

### Expected Metrics:
- Animation frame rate: 60fps
- Time to add slot: <300ms
- Time to remove slot: <400ms
- JavaScript bundle size: Optimized
- CSS rules: No duplicates

## Known Limitations
- Animation gracefully degrades on slower devices
- Reduced motion preference respected
- IE11 support: Basic functionality only

## Test Results Log

**Date:** _______________
**Tester:** _____________
**Browser:** ____________
**Device:** _____________

### Pass/Fail Results:
- [ ] Basic Functionality
- [ ] Visual Design
- [ ] User Experience  
- [ ] Accessibility
- [ ] Performance
- [ ] Cross-browser

**Notes:**
_________________________________________________
_________________________________________________
_________________________________________________

**Issues Found:**
_________________________________________________
_________________________________________________
_________________________________________________