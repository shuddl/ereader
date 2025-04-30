# Good E-Reader Book Recommendation Plugin - Manual Test Plan

This document outlines the manual test plan for the Good E-Reader Book Recommendation WordPress plugin. The test plan covers all major user flows and edge cases in a realistic WordPress environment.

## Test Environment Requirements

- WordPress installation (latest stable version)
- PHP 8.0+
- Administrator access to WordPress
- Valid API keys:
  - OpenAI API key
  - Google Books API key
- Amazon Affiliate tag (`gooderead-20`)
- Multiple browsers for cross-browser testing (Chrome, Firefox, Safari)
- Desktop and mobile devices for responsive testing
- Dev tools for network monitoring and console inspection

## 1. Plugin Activation and Setup

| Test ID | Test Description | Expected Result | Actual Result | Status |
|---------|-----------------|----------------|--------------|--------|
| 1.1 | Upload and activate plugin | Plugin activates without errors | | |
| 1.2 | Access settings page | Settings page loads correctly at Settings → Book Recommender | | |
| 1.3 | Enter and save API keys | OpenAI and Google Books API keys save correctly | | |
| 1.4 | Enter and save Amazon Affiliate tag | Amazon tag saves correctly | | |
| 1.5 | Test invalid API key formats | Validation error messages appear | | |
| 1.6 | Test API key validation | Successful validation messages appear for valid keys | | |
| 1.7 | Set cache duration settings | Cache duration settings save correctly | | |
| 1.8 | Set A/B test settings | A/B test settings save correctly | | |
| 1.9 | Verify default settings are applied on first install | Default settings are present in UI and database | | |

## 2. Shortcode Rendering

| Test ID | Test Description | Expected Result | Actual Result | Status |
|---------|-----------------|----------------|--------------|--------|
| 2.1 | Add `[bookrec]` to a page | Chatbot UI renders correctly on the page | | |
| 2.2 | Add `[bookrec]` to a post | Chatbot UI renders correctly on the post | | |
| 2.3 | Add `[bookrec]` to a widget area | Chatbot UI renders correctly in the widget area | | |
| 2.4 | Add multiple instances on same page | Each chatbot instance works independently | | |
| 2.5 | Check console for JS errors | No JavaScript errors in console | | |
| 2.6 | Check network tab for 404s | No missing resources | | |

## 3. Adaptive Slot-Filling Conversation Flow

| Test ID | Test Description | Expected Result | Actual Result | Status |
|---------|-----------------|----------------|--------------|--------|
| 3.1 | Start conversation with welcome message | Chatbot displays welcome message asking about reading preferences | | |
| 3.2 | Respond with a genre preference | Chatbot acknowledges genre and asks follow-up question about tone/mood | | |
| 3.3 | Respond with tone preference | Chatbot asks about author preferences or book length | | |
| 3.4 | Complete slot-filling conversation | Chatbot generates relevant recommendations based on inputs | | |
| 3.5 | Provide unclear genre answer | Chatbot politely rephrases question or provides genre examples | | |
| 3.6 | Provide unclear tone/mood answer | Chatbot clarifies and offers suggestions | | |
| 3.7 | Test conversation with one-word answers | Chatbot handles minimal input appropriately | | |
| 3.8 | Test conversation with detailed answers | Chatbot extracts relevant information from verbose input | | |
| 3.9 | Ask unrelated question mid-conversation | Chatbot politely redirects to book recommendation context | | |
| 3.10 | Test conversation memory within session | Chatbot remembers previously mentioned preferences | | |

## 4. Recommendation Generation and Display

| Test ID | Test Description | Expected Result | Actual Result | Status |
|---------|-----------------|----------------|--------------|--------|
| 4.1 | Request recommendations for popular genre (e.g., Thriller) | 3-5 relevant thriller recommendations appear | | |
| 4.2 | Request recommendations for niche genre (e.g., Cyberpunk) | Relevant cyberpunk recommendations appear | | |
| 4.3 | Verify book cover images | Cover images load correctly | | |
| 4.4 | Verify book metadata | Book title, author, description, etc. appear correctly | | |
| 4.5 | Verify Amazon affiliate links | Links include correct Amazon URLs with affiliate tag | | |
| 4.6 | Verify CTA button text (A/B test variations) | CTA buttons show either "View Price on Amazon" or "Learn More & Buy" | | |
| 4.7 | Intentionally trigger Google Books API failure (use invalid API key temporarily) | Fallback JSON recommendations appear | | |
| 4.8 | Request recommendations for obscure/nonexistent titles | Chatbot gracefully handles this with fallback recommendations or helpful message | | |
| 4.9 | Verify recommendation explanations | Explanatory text before recommendations explains why they were chosen | | |
| 4.10 | Verify concluding questions | Concluding question after recommendations asks for feedback or refinement | | |

## 5. Author Search Functionality

| Test ID | Test Description | Expected Result | Actual Result | Status |
|---------|-----------------|----------------|--------------|--------|
| 5.1 | Add `[bookrec author_search="true"]` to a page | Author search input appears in the UI | | |
| 5.2 | Search for popular author (e.g., "Stephen King") | Returns books by Stephen King | | |
| 5.3 | Search for author with special characters (e.g., "García Márquez") | Handles special characters correctly | | |
| 5.4 | Search for nonexistent author | Displays appropriate "no results" message | | |
| 5.5 | Trigger author search through conversation ("Show me books by <author>") | Returns relevant books by that author | | |

## 6. Genre Filter (Shortcode Parameter)

| Test ID | Test Description | Expected Result | Actual Result | Status |
|---------|-----------------|----------------|--------------|--------|
| 6.1 | Add `[bookrec genre="Science Fiction"]` to a page | Chatbot initializes with Science Fiction focus | | |
| 6.2 | Check initial prompt with genre filter | Initial message mentions the genre focus | | |
| 6.3 | Request recommendations with genre filter active | Recommendations prioritize the specified genre | | |
| 6.4 | Ask about different genre with genre filter active | Chatbot acknowledges request but maintains some focus on specified genre | | |

## 7. Affiliate Link Click Tracking

| Test ID | Test Description | Expected Result | Actual Result | Status |
|---------|-----------------|----------------|--------------|--------|
| 7.1 | Click on recommendation Amazon link | Link opens Amazon in new tab and click is tracked | | |
| 7.2 | Verify AJAX request for click tracking in network tab | AJAX request to `bookrec_track_click` action is sent | | |
| 7.3 | Check database for click records | Click record appears in `wp_bookrec_clicks` table with correct data | | |
| 7.4 | Click links with different CTA variations | Both variations track correctly with variation data | | |
| 7.5 | Test click tracking with network disabled | Link still opens even if tracking fails | | |

## 8. Session Learning (localStorage)

| Test ID | Test Description | Expected Result | Actual Result | Status |
|---------|-----------------|----------------|--------------|--------|
| 8.1 | Start chat and mention genre preference | Chatbot acknowledges genre preference | | |
| 8.2 | Reload page/tab | Session continues with history preserved | | |
| 8.3 | Close and reopen browser | Session data persists via localStorage | | |
| 8.4 | Start new conversation after previous session | Chatbot subtly acknowledges previous preferences | | |
| 8.5 | Clear localStorage and restart | Chatbot treats as completely new session | | |
| 8.6 | Test with multiple instances on different pages | Each maintains its own session state | | |

## 9. Email PDF Opt-In

| Test ID | Test Description | Expected Result | Actual Result | Status |
|---------|-----------------|----------------|--------------|--------|
| 9.1 | Enter valid email in PDF request form | Success message appears | | |
| 9.2 | Check email for PDF attachment | Email arrives with PDF of recommendations | | |
| 9.3 | Verify PDF content | PDF contains correct recommendations and formatting | | |
| 9.4 | Enter invalid email format | Validation error appears | | |
| 9.5 | Submit without email | Validation error appears | | |
| 9.6 | Test with email service disabled | Appropriate error message appears | | |

## 10. Share Card Functionality

| Test ID | Test Description | Expected Result | Actual Result | Status |
|---------|-----------------|----------------|--------------|--------|
| 10.1 | Click "Share" button on recommendation | Share dialog appears (Web Share API) or link is copied | | |
| 10.2 | Visit generated share URL | Book share page loads with correct book data | | |
| 10.3 | Check meta tags on share page | Open Graph and Twitter meta tags contain correct book info | | |
| 10.4 | Test share on social media platform | Preview shows book cover and description | | |
| 10.5 | Test share fallback (systems without Web Share API) | Copy to clipboard mechanism works | | |

## 11. Dark Mode Adaptation

| Test ID | Test Description | Expected Result | Actual Result | Status |
|---------|-----------------|----------------|--------------|--------|
| 11.1 | View chatbot with system in light mode | Light theme appears with appropriate colors | | |
| 11.2 | Switch system to dark mode | UI adapts to dark theme with appropriate colors | | |
| 11.3 | Test transitions between modes | Smooth transition animation between modes | | |
| 11.4 | Verify contrast and readability in dark mode | Text remains readable with sufficient contrast | | |
| 11.5 | Check all UI elements in dark mode | All UI elements adapt correctly to dark mode | | |

## 12. Error Handling

| Test ID | Test Description | Expected Result | Actual Result | Status |
|---------|-----------------|----------------|--------------|--------|
| 12.1 | Use invalid OpenAI API key | User-friendly error appears | | |
| 12.2 | Use invalid Google Books API key | Graceful fallback to JSON data | | |
| 12.3 | Disable network during conversation | Error message about connectivity appears | | |
| 12.4 | Restore network and continue conversation | Conversation recovers correctly | | |
| 12.5 | Test with API rate limits exceeded | Appropriate error message appears | | |
| 12.6 | Test with very slow network | Loading indicators show during delays | | |

## 13. Responsiveness

| Test ID | Test Description | Expected Result | Actual Result | Status |
|---------|-----------------|----------------|--------------|--------|
| 13.1 | View on desktop (1920×1080) | UI renders correctly and is fully functional | | |
| 13.2 | View on tablet (768×1024) | UI adapts to tablet size | | |
| 13.3 | View on mobile (375×667) | UI adapts to mobile size | | |
| 13.4 | Test orientation change on mobile | UI adapts smoothly to orientation change | | |
| 13.5 | Test with mobile keyboard open | UI adjusts correctly when keyboard appears | | |

## 14. Security and Performance

| Test ID | Test Description | Expected Result | Actual Result | Status |
|---------|-----------------|----------------|--------------|--------|
| 14.1 | Verify AJAX nonce validation | Requests without valid nonce are rejected | | |
| 14.2 | Check API key storage in database | API keys are stored securely | | |
| 14.3 | Check for exposed API keys in frontend | No API keys are visible in page source or JavaScript | | |
| 14.4 | Test XSS prevention in chat | HTML/script tags in chat are properly escaped | | |
| 14.5 | Monitor memory usage during extended conversations | Memory usage remains stable | | |
| 14.6 | Check caching of Google Books API requests | Cached responses are used for repeat queries | | |

## 15. Admin Dashboard Widget

| Test ID | Test Description | Expected Result | Actual Result | Status |
|---------|-----------------|----------------|--------------|--------|
| 15.1 | Check widget presence in WP Admin dashboard | Widget appears in dashboard | | |
| 15.2 | Verify click metrics display | Click count metrics are displayed correctly | | |
| 15.3 | Verify A/B test data in widget | Conversion rates for different CTA variations are displayed | | |
| 15.4 | Test widget refresh | Data refreshes correctly | | |
| 15.5 | Check recommendations/sessions count | Metrics show accurate counts | | |

## Test Execution Log

| Date | Tester | Environment | Test Range | Issues Found | Notes |
|------|--------|-------------|------------|--------------|-------|
|      |        |             |            |              |       |

## Bug Report Template

For any issues found during testing, please use the following template:

**Bug ID**: (auto-increment number)  
**Test Case ID**: (related test case)  
**Severity**: (Critical/High/Medium/Low)  
**Description**: (clear description of the issue)  
**Steps to Reproduce**:
1. Step 1
2. Step 2
3. etc.

**Expected Result**: (what should happen)  
**Actual Result**: (what actually happens)  
**Screenshots/Videos**: (if applicable)  
**Environment**: (browser, OS, device)  
**Additional Context**: (any other relevant information)

## Test Completion Checklist

- [ ] All test cases have been executed
- [ ] All critical and high severity issues have been addressed
- [ ] Performance is acceptable across devices
- [ ] Security requirements have been validated
- [ ] Documentation is accurate and complete
- [ ] User experience is consistent and intuitive

## Sign-off

After completing all tests and addressing any critical issues, the testing team should sign off:

**Testing Completed By**:  
**Date**:  
**Recommendation**: (Ready for Deployment / Needs Additional Work)