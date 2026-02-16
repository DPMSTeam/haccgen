<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * English language strings for local_haccgen.
 *
 * @package     local_haccgen
 * @copyright   2026 Dynamicpixel Multimedia Solutions
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['About'] = '코스 소개';
$string['TOPICTITLE'] = '주제 제목';
$string['TOPICTITLE_help'] = '코스의 이름을 입력하세요 (예: Web Development 101).';
$string['TOPICTITLE_placeholder'] = '주제 제목 입력';
$string['aboutthiscourse'] = '코스 소개';
$string['addtopic'] = '주제 추가';
$string['advanced'] = '고급';
$string['apierror'] = 'API 콘텐츠 생성 실패: {error}';
$string['apikey'] = 'API 키';
$string['apikey_desc'] = 'AI API 인증에 필요한 키를 입력하세요.';
$string['apisecret'] = 'API 비밀키';
$string['apisecret_desc'] = 'API 비밀키를 입력하세요.';
$string['apisettings'] = 'API 설정';
$string['apitimeout'] = 'API 요청 제한 시간';
$string['apitimeout_desc'] = 'API 응답을 기다리는 최대 시간(초).';
$string['apiurl'] = 'API URL';
$string['apiurl_desc'] = '콘텐츠 생성에 사용되는 API URL을 입력하세요.';
$string['areyousure'] = '정말 이 초안을 삭제하시겠습니까?';
$string['audience'] = '대상';
$string['back'] = '뒤로';
$string['basics'] = '기본 정보';
$string['beginner'] = '초급';
$string['cancel'] = '취소';
$string['clicksubtopiccontent'] = '하위 주제를 클릭하면 해당 내용을 볼 수 있습니다.';
$string['completion_both'] = '슬라이드 보기 + 퀴즈 통과 시 완료';
$string['completion_condition'] = '완료 조건';
$string['completion_on_last_button'] = '마지막 슬라이드에서 Finish 버튼 클릭 시';
$string['completion_on_launch'] = '코스 시작 시';
$string['completion_on_view_all'] = '모든 슬라이드를 본 후';
$string['completion_passquiz'] = '퀴즈 통과 시 완료';
$string['completion_viewslides'] = '모든 슬라이드 보기 시 완료';
$string['completionmode'] = '완료 조건';
$string['completionmode_desc'] = 'SCORM 활동이 완료로 표시되는 조건을 선택하세요.';
$string['conclusion'] = '결론';
$string['content'] = '콘텐츠';
$string['content_generated'] = '코스 콘텐츠가 성공적으로 생성되었습니다!';
$string['contentgenerationfailed'] = '콘텐츠 생성 실패: {$a}';
$string['contentwillappear'] = '하위 주제를 선택하면 콘텐츠가 여기에 표시됩니다.';
$string['continue'] = '계속';
$string['course_title_default'] = '제목 없는 코스';
$string['courseduration'] = '코스 소요 시간';
$string['courseduration_help'] = '코스를 완료하는 데 걸리는 대략적인 시간을 선택하세요.';
$string['courseduration_label'] = '소요 시간';
$string['courseisfor'] = '{$a->title} 코스는 {$a->audience}을(를) 위해 설계되었습니다.';
$string['courselanguage'] = '코스 언어';
$string['courseoutline'] = '코스 개요';
$string['createhaccgen'] = '폼 기반 AI 코스 생성';
$string['custom_prompt'] = '사용자 지정 프롬프트';
$string['debuglogging'] = '디버그 로그 활성화';
$string['debuglogging_desc'] = '디버깅을 위한 상세 API 로그를 활성화합니다.';
$string['defaultduration'] = '기본 코스 시간';
$string['defaultduration_desc'] = '사용자가 시간을 입력하지 않은 경우 기본값으로 사용됩니다.';
$string['deletedraftbtn'] = '초안 삭제';
$string['description'] = '설명';
$string['details'] = '세부 내용';
$string['disclaimer_text'] = '<strong>주의:</strong> HACC Gen이 생성한 콘텐츠는 AI 기반이며 최종 사용 전에 검토 및 수정이 필요합니다.';
$string['draftdeletedsuccess'] = '초안 삭제 완료.';
$string['draftnotfound'] = '초안을 찾을 수 없습니다.';
$string['draftsaved'] = '초안 저장 완료.';
$string['draftsavedsuccess'] = '초안이 저장되었습니다. 계속 진행할 수 있습니다.';
$string['duration_10min'] = '약 10분';
$string['duration_120minutes'] = '약 120분';
$string['duration_15min'] = '약 15분';
$string['duration_30min'] = '약 30분';
$string['duration_60minutes'] = '약 60분';
$string['duration_90minutes'] = '약 90분';
$string['editquizquestions'] = '퀴즈 질문 편집';
$string['enablepdfupload'] = 'PDF 업로드 허용';
$string['enablepdfupload_desc'] = '1단계에서 PDF 업로드를 허용합니다.';
$string['engaging'] = '흥미로운';
$string['english'] = '영어';
$string['error_api'] = 'API 통신 실패: {$a}';
$string['error_api_empty'] = 'API에서 주제를 반환하지 않았습니다.';
$string['error_course_creation'] = '코스 생성 실패: {$a}';
$string['error_numeric'] = '양의 숫자를 입력해야 합니다.';
$string['error_required'] = '이 필드는 필수입니다.';
$string['error_required_fields'] = '모든 필수 항목을 입력해 주세요.';
$string['finalize'] = '마무리';
$string['formal'] = '격식 있는';
$string['generate'] = '코스 생성';
$string['generatecontent'] = '계속';
$string['generated_topics'] = '생성된 주제';
$string['generated_topics_list'] = '생성된 주제 목록';
$string['generatescorm'] = 'SCORM 패키지 생성';
$string['generatingcourse'] = '코스를 생성하는 중…';
$string['hindi'] = '힌디어';
$string['inactive'] = '구독이 만료되었습니다. info@dynamicpixel.co.in으로 문의하세요.';
$string['instructiontext'] = '안내 텍스트';
$string['instructiontext_desc'] = '코스 생성 페이지 상단에 표시될 안내 문구입니다.';
$string['intermediate'] = '중급';
$string['invalid_pdf'] = '유효한 PDF 파일을 업로드하세요.';
$string['invalid_topic_count'] = '주제 수는 2~10 사이여야 합니다.';
$string['invalidcontentdata'] = '잘못된 콘텐츠 데이터가 제공되었습니다.';
$string['invalidjson'] = '잘못된 JSON: {$a}';
$string['invalidtopicorder'] = '잘못된 주제 순서: {$a}';
$string['js_error'] = '오류가 발생했습니다. 다시 시도해 주세요.';
$string['language'] = '언어 선택';
$string['learningobjectives'] = '학습 목표';
$string['learningobjectivesfor'] = '학습 목표 – {$a}';
$string['levelofunderstanding'] = '이해 수준';
$string['levelofunderstanding_help'] = '코스 콘텐츠의 난이도를 선택하세요.';
$string['levelofunderstanding_label'] = '수준';
$string['linksecret'] = '서명 링크 비밀키';
$string['linksecret_desc'] = '임시 파일 URL 서명에 사용되는 비밀 키입니다.';
$string['loaddraft'] = '이전 초안 불러오기';
$string['loaddraftbtn'] = '초안 불러오기';
$string['loadingquestions'] = '질문 불러오는 중...';
$string['log_hasquiz'] = '퀴즈 포함 여부';
$string['log_quizlist'] = '퀴즈 목록';
$string['log_totalquizzes'] = '총 퀴즈 수';
$string['log_totalslides'] = '총 슬라이드 수';
$string['manageai'] = 'HACC Gen';
$string['maxtopics'] = '최대 주제 수';
$string['maxtopics_desc'] = '생성 가능한 주제의 최대 수 (권장: 5–20).';
$string['missingcredentials'] = 'API 인증 정보가 없습니다.';
$string['missingfield'] = '필수 필드 누락: {$a}';
$string['no'] = '아니오';
$string['no_content_generated'] = '생성된 콘텐츠가 없습니다. 다시 시도해 주세요.';
$string['no_topics'] = '주제가 생성되지 않았습니다. 입력값을 확인하세요.';
$string['no_topics_error'] = '코스를 생성할 주제가 없습니다.';
$string['noapiurl'] = 'API URL이 구성되지 않았습니다.';
$string['nocontent'] = '콘텐츠가 없습니다.';
$string['nocontentavailable'] = '콘텐츠가 없습니다.';
$string['nodraftsfound'] = '이 코스에는 저장된 초안이 없습니다.';
$string['noendpoint'] = '구독 서비스가 엔드포인트를 반환하지 않았습니다.';
$string['noquizavailable'] = '사용 가능한 퀴즈가 없습니다.';
$string['nosubtopics'] = '이 주제에는 하위 주제가 없습니다.';
$string['not_selected'] = '선택되지 않음';
$string['notopics'] = '사용 가능한 주제가 없습니다.';
$string['numberoftopics'] = '주제 개수';
$string['passgrade'] = '합격 점수 (%)';
$string['passgrade_desc'] = '퀴즈/SCORM 합격을 위해 필요한 최소 점수입니다.';
$string['passingscore'] = '기본 합격 점수';
$string['passingscore_desc'] = 'SCORM/퀴즈를 통과하기 위한 최소 점수입니다.';
$string['pdfuploaded'] = 'PDF 업로드 완료';
$string['please_complete'] = '양식을 모두 작성하세요.';
$string['please_select'] = '올바른 옵션을 선택하세요.';
$string['pluginname'] = 'HACC Gen';
$string['previewtitle'] = '미리보기';
$string['proceed'] = '계속';
$string['provider_desc'] = '콘텐츠 생성에 사용할 AI 제공자를 선택하세요.';
$string['provider_for_content'] = '콘텐츠 제공자 (4단계)';
$string['provider_for_content_outline'] = '콘텐츠 개요 제공자 (3단계)';
$string['publiclinkttl'] = '공개 링크 만료 시간';
$string['publiclinkttl_desc'] = '공개 링크가 유효한 시간 (예: 1시간).';
$string['quizdefault'] = '퀴즈 {$a}';
$string['quizpreview'] = '퀴즈 미리보기';
$string['quotes'] = '시작해봅시다… 당신의 학습 목표 뒤에 숨겨진 목적을 알아봅시다';
$string['returntostep1'] = '1단계로 돌아가기';
$string['review'] = '검토';
$string['save'] = '저장';
$string['saveandcontinue'] = '저장 후 계속';
$string['savechanges'] = '변경 사항 저장';
$string['savecreate'] = '저장하고 코스 생성';
$string['scoring_mixed'] = '혼합 (퀴즈 50% + 슬라이드 50%)';
$string['scoring_quiz_only'] = '퀴즈만 (100% 퀴즈 점수)';
$string['scoringmethod'] = '채점 방식';
$string['scoringmethod_desc'] = '최종 점수를 계산하는 방식을 선택하세요.';
$string['scorm_summary'] = 'SCORM 패키지 요약';
$string['scormsettings'] = 'SCORM 설정';
$string['scormtype'] = 'SCORM 생성 유형';
$string['scormtype_desc'] = 'Single SCO 또는 Multi SCO를 선택하세요.';
$string['scormtype_multi'] = 'Multi SCO';
$string['scormtype_single'] = 'Single SCO 1.2';
$string['scormversion'] = '기본 SCORM 버전';
$string['scormversion1.2'] = 'SCORM 1.2';
$string['scormversion2004'] = 'SCORM 2004';
$string['scormversion_desc'] = '생성할 SCORM 버전을 선택하세요.';
$string['sectioncreationfailed'] = '코스 섹션 생성 실패: {$a}';
$string['select_duration'] = '시간 선택';
$string['select_language'] = '언어 선택';
$string['select_level'] = '수준 선택';
$string['select_tone'] = '서술 방식 선택';
$string['selectdraft'] = '초안 선택:';
$string['selectsubtopic'] = '하위 주제를 선택하여 콘텐츠를 확인하세요.';
$string['settings_desc'] = 'AI 코스 생성기 설정을 구성하세요.';
$string['step1'] = '1단계: 코스 정보';
$string['step2'] = '2단계: 학습 선호도';
$string['step3'] = '3단계: 주제 검토';
$string['step_progress'] = '코스 생성 진행 상황';
$string['subscription_url'] = '구독 서비스 URL';
$string['subscription_url_desc'] = '구독 관리자 API의 URL을 입력하세요.';
$string['subscriptionexpired'] = '콘텐츠 생성 서비스가 현재 사용 불가입니다.';
$string['summary'] = '요약';
$string['target_audience_default'] = '일반 대상';
$string['targetaudience'] = '대상 학습자';
$string['targetaudience_help'] = '이 코스는 누구를 위한 것인가요? 예: 학생, 직장인. 여러 태그를 추가할 수 있습니다.';
$string['targetaudience_list'] = '대상 태그 목록';
$string['targetaudience_placeholder'] = '대상 태그 입력 (Enter 키로 추가)';
$string['tinymce_height'] = 'TinyMCE 높이';
$string['tinymce_height_desc'] = '편집기의 높이(px).';
$string['tinymce_plugins'] = 'TinyMCE 플러그인';
$string['tinymce_plugins_desc'] = 'AI 코스 편집기에 사용할 TinyMCE 플러그인 목록입니다.';
$string['tinymce_toolbar'] = 'TinyMCE 도구 모음';
$string['tinymce_toolbar_desc'] = 'TinyMCE 도구 모음 구성.';
$string['toneofnarrative'] = '서술 방식';
$string['toneofnarrative_help'] = '코스 콘텐츠의 서술 스타일을 선택하세요.';
$string['topics'] = '주제';
$string['trackallpages'] = '모든 페이지 추적';
$string['trackingmode'] = '추적 모드';
$string['trackingmode_desc'] = 'SCORM 페이지 추적 방식을 선택하세요.';
$string['tracklastslide'] = '마지막 슬라이드 및 퀴즈만 추적';
$string['unauthorized'] = '이 요청을 수행할 권한이 없습니다.';
$string['untitledsubtopic'] = '제목 없는 하위 주제';
$string['upload_error'] = '업로드 중 오류가 발생했습니다.';
$string['upload_pdf'] = 'PDF 업로드';
$string['yes'] = '예';
