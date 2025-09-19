<?php
defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'HACC Gen';
$string['manageai'] = 'HACC Gen';
$string['TOPICTITLE'] = 'Topic Title';
$string['TOPICTITLE_help'] = 'The name of your course (e.g., Web Development 101).';
$string['TOPICTITLE_placeholder'] = 'Enter topic title';
$string['targetaudience'] = 'Target Audience';
$string['targetaudience_help'] = 'Who this course is for, e.g., Students, Professionals (add multiple tags).';
$string['targetaudience_placeholder'] = 'Add audience tags (press Enter to add)';
$string['targetaudience_list'] = 'List of target audience tags';
$string['generatecontent'] = 'Continue';
$string['cancel'] = 'Cancel';
$string['error_required'] = 'This field is required.';
$string['error_numeric'] = 'This field must be a positive number.';
$string['error_required_fields'] = 'Please complete all required fields.';
$string['previewtitle'] = 'Preview';
$string['quotes'] = "Let's Start.. Uncovering the Purpose Behind Your Learning Goals";
$string['step1'] = 'Step 1: Course Details';
$string['step2'] = 'Step 2: Learning Preferences';
$string['step3'] = 'Step 3: Review Topics';
$string['step_progress'] = 'Course creation progress';
$string['levelofunderstanding'] = 'Level of Understanding';
$string['levelofunderstanding_help'] = 'Select the difficulty level of the course content.';
$string['levelofunderstanding_label'] = 'Level';
$string['select_level'] = 'Select level';
$string['beginner'] = 'Beginner';
$string['intermediate'] = 'Intermediate';
$string['advanced'] = 'Advanced';
$string['courseduration'] = 'Course Duration';
$string['courseduration_help'] = 'Select the approximate duration of the course.';
$string['courseduration_label'] = 'Duration';
$string['select_duration'] = 'Select duration';
$string['duration_10min'] = 'Approximately 10 minutes';
$string['duration_15min'] = 'Approximately 15 minutes';
$string['duration_30min'] = 'Approximately 30 minutes';
$string['back'] = 'Back';
$string['continue'] = 'Continue';
$string['generate'] = 'Generate Course';
$string['please_select'] = 'Please select options';
$string['please_complete'] = 'Please complete form';
$string['js_error'] = 'An error occurred. Please try again.';
$string['content_generated'] = 'Course content generated successfully!';
$string['generated_topics'] = 'Generated Course Topics';
$string['generated_topics_list'] = 'List of generated course topics';
$string['no_topics'] = 'No topics generated. Please go back and check your inputs.';
$string['no_topics_error'] = 'No topics available to generate the course. Please go back and try again.';
$string['course_title_default'] = 'Untitled Course';
$string['target_audience_default'] = 'General Audience';
$string['not_selected'] = 'Not selected';
$string['missingfield'] = 'Missing required field: {$a}';
$string['contentgenerationfailed'] = 'Failed to generate content: {$a}';
$string['invalidcontentdata'] = 'Invalid content data provided.';
$string['sectioncreationfailed'] = 'Failed to create course sections: {$a}';
$string['topics'] = 'topics';
$string['description'] = 'Description';
$string['toneofnarrative'] = 'Tone of Narrative';
$string['toneofnarrative_help'] = 'Select the narrative style for the course content.';
$string['select_tone'] = 'Select tone';
$string['formal'] = 'Formal';
$string['conversational'] = 'Conversational';
$string['engaging'] = 'Engaging';
$string['invalid_pdf'] = 'Invalid or missing PDF file. Please upload a valid PDF.';
$string['upload_pdf'] = 'Upload PDF';
$string['topics'] = 'Topics';
$string['content'] = 'Content';
$string['back'] = 'Back';
$string['save'] = 'Save';
$string['contentgenerationfailed'] = 'Content generation failed: {$a}';
$string['invalidtopicorder'] = 'Invalid topic order: {$a}';

$string['invalid_pdf'] = 'Invalid or missing PDF file.';
$string['upload_error'] = 'Error uploading file.';
$string['please_select'] = 'Please select a valid option.';
$string['invalidcontentdata'] = 'Invalid content data provided.';
$string['invalidtopicorder'] = 'Invalid topic order: {$a}';
$string['contentgenerationfailed'] = 'Content generation failed: {$a}';
$string['content_generated'] = 'Course content generated successfully.';
$string['invalidstep'] = 'Invalid step: {$a}';
$string['audience'] = 'Audience';
$string['courseisfor'] = 'This {$a->title} course is designed for {$a->audience}';

$string['toneofnarrative'] = 'Tone of Narrative';

$string['pdfuploaded'] = 'Uploaded PDF';
$string['addtopic'] = 'Add More Topics';
$string['back'] = 'Back';

$string['basics'] = 'Basics';
$string['details'] = 'Details';
$string['review'] = 'Review';
$string['introduction'] = 'Introduction';
$string['summary'] = 'Summary';
$string['conclusion'] = 'Conclusion';
$string['finalize'] = 'Finalize';
$string['selectsubtopic'] = 'Select Subtopic to see content';

$string['createhaccgen'] = 'Create AI Course from Form';
$string['no_topics_error'] = 'No topics were generated or provided.';
$string['sectioncreationfailed'] = 'Failed to create or update course sections: {$a}';


$string['apiurl'] = 'Moodle Content Platform API URL';
$string['apiurl_desc'] = 'Enter the base URL for the Moodle Content Platform API (e.g., https://your-api-gateway-url.execute-api.ap-south-1.amazonaws.com).';
$string['noapiurl'] = 'API URL is not configured for the Moodle Content Platform.';
$string['apierror'] = 'Failed to generate content from the API: {error}';


$string['invalid_pdf'] = 'The uploaded file must be a valid PDF.';
$string['upload_error'] = 'An error occurred while uploading the file.';
$string['please_select'] = 'Please select a valid option.';
$string['invalidjson'] = 'Invalid JSON data: {$a}.';
$string['invalidtopicorder'] = 'Invalid topic order: {$a}.';
$string['contentgenerationfailed'] = 'Failed to generate content: {$a}.';
$string['no_content_generated'] = 'No content was generated. Please try again.';
$string['content_generated'] = 'Course content successfully generated!';


$string['settings_desc'] = 'Configure settings for the AI Course Generator plugin to customize course creation and API integration.';
$string['apiurl'] = 'API URL';
$string['apiurl_desc'] = 'Enter the URL of the AI API endpoint used for generating course content (e.g., https://api.x.ai/v1/course). Ensure the URL is valid and accessible.';
$string['apikey'] = 'API Key';
$string['apikey_desc'] = 'Enter the API key for authenticating with the AI API. Keep this secure and do not share it publicly.';
$string['apitimeout'] = 'API Request Timeout';
$string['apitimeout_desc'] = 'Set the maximum time (in seconds) to wait for API responses. Recommended: 30-60 seconds.';
$string['enablepdfupload'] = 'Enable PDF Upload';
$string['enablepdfupload_desc'] = 'Allow users to upload a PDF file in Step 1 to generate course content from the document.';
$string['maxtopics'] = 'Maximum Number of Topics';
$string['maxtopics_desc'] = 'Set the maximum number of topics that can be generated for a course. Recommended: 5-20.';
$string['defaultduration'] = 'Default Course Duration';
$string['defaultduration_desc'] = 'Select the default duration for courses when not specified by the user.';
$string['debuglogging'] = 'Enable Debug Logging';
$string['debuglogging_desc'] = 'Enable detailed logging for API calls and errors. Useful for troubleshooting, but disable in production to avoid performance overhead.';



$string['invalid_pdf'] = 'The uploaded file is not a valid PDF.';
$string['upload_error'] = 'An error occurred while uploading the file.';
$string['error_api'] = 'Failed to communicate with the AI API: {$a}.';
$string['error_api_empty'] = 'The AI API returned no topics. Please check your input and try again.';
$string['error_course_creation'] = 'Failed to create course: {$a}.';
$string['invalidtopicorder'] = 'Invalid topic order: {$a}.';
$string['contentgenerationfailed'] = 'Content generation failed: {$a}.';
$string['content_generated'] = 'Course content successfully generated!';
$string['nocontent'] = 'No content was generated. Please try again.';

$string['content_generated'] = 'AI-generated course created successfully.';
$string['invalid_pdf'] = 'Please upload a valid PDF file.';
$string['invalidjson'] = 'Invalid JSON format.';
$string['invalidtopicorder'] = 'Topic structure could not be parsed: {$a}';

$string['please_select'] = 'Please select an option.';
$string['contentgenerationfailed'] = 'Content generation failed: {$a}';
$string['notopics'] = 'No topics were generated. Please go back and check your inputs.';
$string['invalidparam'] = 'Invalid parameter: {$a}';
$string['step3_title'] = 'Review Generated Topics';


$string['missingparam'] = 'Missing required field: {$a}';
$string['invalidjson'] = 'Invalid JSON for: {$a}';
$string['invalid_pdf'] = 'Please upload a valid PDF file.';

$string['please_select'] = 'Please select a valid option.';
$string['contentgenerationfailed'] = 'Content generation failed: {$a}';
$string['no_topics_generated'] = 'No topics were generated. Please try again.';
$string['saveandcontinue'] = 'Save and Continue';

$string['edit'] = 'Edit';
$string['delete'] = 'Delete';
$string['back'] = 'Back';
$string['content_generated'] = 'Course content generated successfully.';
$string['duration_15minutes'] = 'Approximately 15 minutes';
$string['duration_30minutes'] = 'Approximately 30 minutes';
$string['duration_10minutes'] = 'Approximately 10 minutes';
$string['duration_60minutes'] = 'Approximately 60 minutes';

$string['duration_90minutes'] = 'Approximately 90 minutes';

$string['duration_120minutes'] = 'Approximately 120 minutes';




$string['notopics'] = 'No topics available.';
$string['clicksubtopiccontent'] = 'Click on a subtopic to view its content.';
$string['nocontent'] = 'No content available.';
$string['save'] = 'Save';

$string['contentwillappear'] = 'Content will appear here after you select a subtopic.';
$string['courseoutline'] = 'Content Outline';
$string['savecreate'] = 'Save and Create Course';




$string['settings_desc'] = 'Configure settings for the AI Course Generator plugin.';
$string['tinymce_plugins'] = 'TinyMCE Plugins';
$string['tinymce_plugins_desc'] = 'List of TinyMCE plugins to enable for the AI Course editor.';
$string['tinymce_toolbar'] = 'TinyMCE Toolbar';
$string['tinymce_toolbar_desc'] = 'Configuration for the TinyMCE toolbar in the AI Course editor.';
$string['tinymce_height'] = 'TinyMCE Editor Height';
$string['tinymce_height_desc'] = 'Height of the TinyMCE editor in pixels.';

$string['instructiontext'] = 'Instruction text';
$string['instructiontext_desc'] = 'This text will appear at the top of the course generation page.';
$string['language'] = 'Select Language';
$string['custom_prompt'] = 'Custom Prompt';
$string['nosubtopics'] = 'No subtopics found for this topic.';


$string['please_select'] = 'Please select a valid option.';
$string['invalid_topic_count'] = 'Please enter a number of topics between 2 and 10.';
$string['courselanguage'] = 'Course Language';
$string['english'] = 'English';
$string['hindi'] = 'Hindi';
$string['select_language'] = 'Select a Language';
$string['numberoftopics'] = 'Number of Topics';


$string['quizpreview'] = 'Quiz Preview';

$string['editquizquestions'] = 'Edit Quiz Questions';
$string['loadingquestions'] = 'Loading questions...';
$string['savechanges'] = 'Save changes';

$string['proceed'] = 'Proceed';
$string['generate'] = 'Generate';
$string['savedraft'] = 'Save Draft';
$string['draftsaved'] = 'Draft saved successfully.';
$string['draftsavedsuccess'] = 'Draft saved successfully. You can continue editing or proceed to the next step.';
$string['generatingcourse'] = 'Generating your course content…';
$string['linksecret'] = 'Signed link secret';
$string['linksecretdesc'] = 'A long random secret used to sign temporary file URLs. Rotating this will invalidate outstanding links.';


$string['apikey'] = 'API Key';
$string['apikey_desc'] = 'Enter your API key here.';
$string['apisecret'] = 'API Secret';
$string['apisecret_desc'] = 'Enter your API secret here. It will be hidden for security.';

$string['returntostep1'] = 'Return to Step 1';
$string['missingcredentials'] = 'API credentials are missing.';
$string['subscriptionexpired'] = 'Content generation is currently unavailable.';
$string['unauthorized'] = 'You are not authorized to do this request.';
$string['inactive'] = 'Your subscription is expire, please contact at info@dynamicpixel.co.in.';
$string['noendpoint'] = 'No content endpoint returned by the subscription service.';
$string['provider_for_content_outline'] = 'Provider for content outline(step3)';
$string['provider_for_content'] = 'Provider for content(step4)';
$string['provider_desc'] = 'Choose which AI provider to use for course generation.';
$string['apisettings'] = 'API Settings';
$string['linksecret_desc'] = 'Secret used to sign public links.';
$string['publiclinkttl'] = 'Public link expiry time';
$string['publiclinkttl_desc'] = 'How long public links remain valid (e.g., 1 hour).';
$string['subscription_url'] = 'Subscription URL';
$string['subscription_url_desc'] = 'Enter the URL of the subscription manager API.';


$string['loaddraft'] = 'Load Previous Draft';
$string['draftdeletedsuccess'] = 'Draft deleted successfully.';
$string['draftnotfound'] = 'Draft not found.';
$string['nodraftsfound'] = 'No draft content found for this course.';
$string['selectdraft'] = 'Select a Draft:';
$string['loaddraftbtn'] = 'Load Draft';
$string['deletedraftbtn'] = 'Delete Draft';
$string['areyousure'] = 'Are you sure you want to delete this draft?';
$string['learningobjectives'] = 'Learning Objectives';