<?php

/**
 * Survey Class Controller
 */
class Survey extends Controller {

  // a score < than will not allow user to perform the survey
  private $threshold_score;

  private $survey_type;

  private $num_warm_up_questions;

  private $num_questions;

  private $set_of_questions;

  // can same user perform same survey several times?
  private $allow_multiple_attempts;

  private $prolific_token;

  // types of questions
  private $RATE_QUESTION_STR = "rate";
  private $FORCED_CHOICE_QUESTION_STR = "forced_choice";

  /**
   * Constructor
   */
  function __construct() {
    parent::__construct();

    if (Session::get('token') !== null) {
      // to avoid reading parameters over and over
      $this->survey_type = Session::get('survey_type');
      $this->num_warm_up_questions = Session::get('num_warm_up_questions');
      $this->num_questions = Session::get('num_questions');
      $this->set_of_questions = Session::get('set_of_questions');
      $this->allow_multiple_attempts = Session::get('allow_multiple_attempts');
      $this->prolific_token = Session::get('prolific_token');
      $this->threshold_score = Session::get('threshold_score');

      return;
    }

    if (!isset($_GET['type_of_survey'])) {
      Session::set('s_errors', array('type_of_survey' => 'There is no type of survey defined.'));
      return;
    }
    $this->survey_type = $_GET['type_of_survey'];
    Session::set('survey_type', $this->survey_type);

    if (!isset($_GET['set_of_questions'])) {
      Session::set('s_errors', array('set_of_questions' => 'There is no set of questions defined.'));
      return;
    }
    $this->set_of_questions = $_GET['set_of_questions'];
    Session::set('set_of_questions', $this->set_of_questions);

    // read configurations
    $configurations = json_decode(file_get_contents(PATH_CONFS . "survey_config.json"), true);
    if (!isset($configurations) || $configurations == null) {
      Session::set('s_errors', array('survey_configuration' => 'It was not possible to read survey parameters.'));
      return;
    }

    if (!isset($configurations['type'])) {
      Session::set('s_errors', array('survey_configuration' => 'It was not possible to initialise all survey parameters.'));
      return;
    }

    $survey_configuration = $configurations[$this->survey_type][0];

    $this->num_warm_up_questions = $survey_configuration['num_warm_up_questions'];
    Session::set('num_warm_up_questions', $this->num_warm_up_questions);

    $this->num_questions = $survey_configuration['num_questions'];
    Session::set('num_questions', $this->num_questions);

    $this->allow_multiple_attempts = (strtolower($survey_configuration['allow_multiple_attempts']) == "no" ? false : true);
    Session::set('allow_multiple_attempts', $this->allow_multiple_attempts);

    $this->prolific_token = $survey_configuration['prolific_token'];
    Session::set('prolific_token', $this->prolific_token);

    if (!isset($this->survey_type) || !isset($this->num_warm_up_questions) || !isset($this->num_questions) || !isset($this->allow_multiple_attempts) || !isset($this->prolific_token)) {
      Session::set('s_errors', array('survey_configuration' => 'Configuration file is not well formed.'));
      return;
    }

    // read competency's configurations
    $configurations = json_decode(file_get_contents(PATH_CONFS . "competency_config.json"), true);
    if (!isset($configurations) || $configurations == null) {
      Session::set('s_errors', array('competency_configuration' => 'It was not possible to read competency parameters.'));
      return;
    }

    $this->threshold_score = $configurations['threshold_score'];
    Session::set('threshold_score', $this->threshold_score);
  }

  /**
   * PAGE: index
   */
  public function index() {

    // Unique token
    $token = uniqid(mt_rand(), true);

    Session::set('token', $token);
    Session::set('question_index', 0);
    Session::set('progress', 0);

    if (isset($_GET['user_id'])) {
      $user_id = preg_replace('/\s+/', '', $_GET['user_id']);

      // has he/she got a score > threshold?
      $user_model = $this->loadModel('user');
      $score = $user_model->getCompetencyScore($user_id);
      if ($score < $this->threshold_score) {
        Session::set('s_errors', array(str_replace('$score', $score, SURVEY_NOT_AVAILABLE_SCORE)));
        header('location: ' . URL);
        return;
      }

      $survey_model = $this->loadModel('survey');
      if (!$this->allow_multiple_attempts && $survey_model->hasUserCompletedSurvey($this->survey_type, $user_id)) {
        Session::set('s_errors', array(str_replace('$user_id', $user_id, ALREADY_DONE_SURVEY)));
        header('location: ' . URL);
        return;
      }

      $this->render($this->survey_type . '/index', array(
        'total_num_warm_up_questions' => $this->num_warm_up_questions,
        'total_num_questions' => $this->num_questions
      ));
    } else {
      header('location: ' . URL);
    }
  }

  /**
   * Handle all actions of this controler
   */
  public function action() {
    $question_index = Session::get('question_index');
    $action_to_perform = ''; // invalid action by default!

    if ($_POST['submit'] == 'Begin') {
      // select all questions for this survey
      $questions = $this->selectQuestions();
      if (!isset($questions) || count($questions) == 0) {
        header('location: ' . URL);
        return;
      }

      Session::set('questions', $questions);

      $action_to_perform = 'survey/question/' . $question_index;
    } else if ($_POST['submit'] == 'Next') {
      if ($this->checkAnswer($question_index)) {
        // as we do not allow users to change any previous answer, we
        // can simple submit every single one to the DB.
        if ($this->submitToDB($question_index)) {
          $question_index++; // next question number
          Session::set('question_index', $question_index);
          $this->progressBar();
        }
      }
      $action_to_perform = 'survey/question/' . $question_index;
    } else if ($_POST['submit'] == 'Exit') {
      $action_to_perform = 'survey/question/' . $question_index;
      if ($this->checkAnswer($question_index)) {
        // as we do not allow users to change any previous answer, we
        // can simple submit every single one to the DB.
        if ($this->submitToDB($question_index)) {
          Session::set('completed', "completed");
          $action_to_perform = 'survey/thanks/';
        }
      }
    }

    header('location: ' . URL . $action_to_perform);
  }

  /**
   *
   */
  private function selectQuestions() {
    if (strtolower($this->survey_type) == $this->RATE_QUESTION_STR) {
      return $this->selectQuestionsForRateSurvey();
    } else if (strtolower($this->survey_type) == $this->FORCED_CHOICE_QUESTION_STR) {
      return $this->selectQuestionsForForcedChoiceSurvey();
    }

    Session::set('s_errors', array('survey' => 'Type of survey not supported.'));
    return null;
  }

  /**
   *
   */
  private function selectQuestionsForRateSurvey() {
    $questions = (array) null;

    $survey_model = $this->loadModel('survey');

    // get all tags from DB
    $all_tags = $survey_model->getAllTags();
    $tags_names = array();
    foreach ($all_tags as $tag) {
      array_push($tags_names, $tag->value);
    }

    // get all snippets from DB and shuffle them
    $all_snippets = $survey_model->getAllSnippets();
    shuffle($all_snippets);

    // warm-up questions

    while (count($questions) < $this->num_warm_up_questions) {
      $question = $this->randomRateQuestion($all_snippets, $questions, $tags_names);
      if ($question != null) {
        $questions = array_merge($questions, $question);
      }
    }

    // prepare questions for the survey

    if ($this->set_of_questions != "-1") {
      $question_index = $this->num_questions * $this->set_of_questions;

      while (count($questions) < $this->num_questions + $this->num_warm_up_questions &&
              $question_index < count($all_snippets)) {
        $snippet = $all_snippets[$question_index];

        // avoid duplicate questions
        if ($this->isDuplicateRateQuestion($questions, $snippet->id)) {
          continue;
        }

        $question = $this->createRateQuestion(count($questions), $tags_names, $snippet);
        $questions = array_merge($questions, $question);
        $question_index++;
      }
    }

    if (count($questions) < $this->num_questions + $this->num_warm_up_questions) {
      // in case the last 'set of questions' reached the limit or user
      // do not want to choose a set of questions, get random ones
      while (count($questions) < $this->num_questions + $this->num_warm_up_questions) {
        $question = $this->randomRateQuestion($all_snippets, $questions, $tags_names);
        if ($question != null) {
          $questions = array_merge($questions, $question);
        }
      }
    }

    return $questions;
  }

  /**
   *
   */
  private function randomRateQuestion($snippets, $questions, $tags) {
    $snippet = $snippets[array_rand($snippets, 1)];

    // avoid duplicate questions
    if ($this->isDuplicateRateQuestion($questions, $snippet->id)) {
      return null;
    }

    $question = $this->createRateQuestion(count($questions), $tags, $snippet);
    return $question;
  }

  /**
   *
   */
  private function isDuplicateRateQuestion($questions, $snippet_id) {
    if ($questions == null) {
      return false;
    }

    foreach ($questions as $question) {
      if ($question['snippet_id'] == $snippet_id) {
        return true;
      }
    }

    return false;
  }

  /**
   *
   */
  private function createRateQuestion($index, $tags, $snippet) {
    $question = array(
      $index => array(
        'snippet_id' => $snippet->id,
        'snippet_path' => $snippet->path,
        'snippet_source_code' => file_get_contents(URL . $snippet->path),
        'time_to_answer' => 0,
        'num_stars' => 0.0,
        'dont_know' => '',
        'comments' => '',
        'tags' => $tags,
        'likes' => array(),
        'dislikes' => array(),
        'warm_up_question' => true ? $index < $this->num_warm_up_questions : false,
        'question_type' => $this->RATE_QUESTION_STR
      )
    );

    return $question;
  }

  /**
   *
   */
  private function selectQuestionsForForcedChoiceSurvey() {
    $questions = (array) null;

    $survey_model = $this->loadModel('survey');

    // get all tags from DB
    $all_tags = $survey_model->getAllTags();
    $tags_names = array();
    foreach ($all_tags as $tag) {
      array_push($tags_names, $tag->value);
    }

    // get all snippets from DB and shuffle them
    $all_snippets = $survey_model->getAllSnippets();
    shuffle($all_snippets);

    // warm-up questions

    while (count($questions) < $this->num_warm_up_questions) {
      $question = $this->randomForcedChoiceQuestion($survey_model, $all_snippets, $questions, $tags_names);
      if ($question != null) {
        $questions = array_merge($questions, $question);
      }
    }

    // prepare questions for the survey

    if ($this->set_of_questions != "-1") {
      $question_index = $this->num_questions * $this->set_of_questions;

      while (count($questions) < $this->num_questions + $this->num_warm_up_questions &&
              $question_index < count($all_snippets)) {
        $selected_snippet_a = $all_snippets[$question_index];
        $selected_snippet_b = $survey_model->getPairSnippet($selected_snippet_a);
        if ($selected_snippet_b === NULL) {
          die("Unfortunately, it was not possible to select a pair for snippet '" . $selected_snippet_a->path . "'!");
        }

        // avoid duplicate questions
        if ($this->isDuplicateForcedChoiceQuestionWithSnippet($questions, $selected_snippet_a->id)) {
          $question_index++;
          continue;
        }

        $question = $this->createForcedChoiceQuestion(count($questions), $tags_names, $selected_snippet_a, $selected_snippet_b);

        $questions = array_merge($questions, $question);
        $question_index++;
      }
    }

    if (count($questions) < $this->num_questions + $this->num_warm_up_questions) {
      // in case the last 'set of questions' reached the limit or user
      // do not want to choose a set of questions, get random ones
      while (count($questions) < $this->num_questions + $this->num_warm_up_questions) {
        $question = $this->randomForcedChoiceQuestion($survey_model, $all_snippets, $questions, $tags_names);
        if ($question != null) {
          $questions = array_merge($questions, $question);
        }
      }
    }

    return $questions;
  }

  /**
   *
   */
  private function randomForcedChoiceQuestion($survey_model, $snippets, $questions, $tags) {
    $selected_snippet_a = $snippets[array_rand($snippets, 1)];
    $selected_snippet_b = $survey_model->getPairSnippet($selected_snippet_a);
    if ($selected_snippet_b === NULL) {
      die("Unfortunately, it was not possible to select a pair for snippet '" . $selected_snippet_a->path . "'!");
    }

    // avoid duplicate questions
    if ($this->isDuplicateForcedChoiceQuestionWithSnippet($questions, $selected_snippet_a->id)) {
      return null;
    }

    $question = $this->createForcedChoiceQuestion(count($questions), $tags, $selected_snippet_a, $selected_snippet_b);
    return $question;
  }

  /**
   *
   */
  private function isDuplicateForcedChoiceQuestionWithSnippet($questions, $snippet_id) {
    if ($questions == null) {
      return false;
    }

    foreach ($questions as $question) {
      if ($question['snippet_a_id'] == $snippet_id || $question['snippet_b_id'] == $snippet_id) {
        return true;
      }
    }

    return false;
  }

  /**
   *
   */
  private function createForcedChoiceQuestion($index, $tags, $snippet_a, $snippet_b) {
    $question = array(
      $index => array(
        'snippet_a_id' => $snippet_a->id,
        'snippet_a_path' => $snippet_a->path,
        'snippet_a_source_code' => file_get_contents(URL . $snippet_a->path),
        'snippet_a_likes' => array(),
        'snippet_a_dislikes' => array(),
        'snippet_b_id' => $snippet_b->id,
        'snippet_b_path' => $snippet_b->path,
        'snippet_b_source_code' => file_get_contents(URL . $snippet_b->path),
        'snippet_b_likes' => array(),
        'snippet_b_dislikes' => array(),
        'chosen_snippet_id' => '',
        'dont_know' => '',
        'comments' => '',
        'tags' => $tags,
        'time_to_answer' => 0,
        'warm_up_question' => true ? $index < $this->num_warm_up_questions : false,
        'question_type' => $this->FORCED_CHOICE_QUESTION_STR
      )
    );

    return $question;
  }

  /**
   *
   */
  public function question($question_index) {
    // if there is no user_id, user should not have access to any question
    $user_id = Session::get('user_id');
    if (!isset($user_id)) {
      header('location: ' . URL);
      return;
    }

    $it_should_be_at_question_index = Session::get('question_index');
    if ($question_index != $it_should_be_at_question_index) {
      header('location: ' . URL . 'survey/question/' . $it_should_be_at_question_index);
      return;
    }

    $questions = Session::get('questions');
    $question = $questions[$question_index];

    // render question based on its type

    if ($question['question_type'] == $this->RATE_QUESTION_STR) {
      $this->render($this->RATE_QUESTION_STR . '/question', array(
        'question_index' => $question_index,
        'progress' => Session::get('progress'),
        'snippet_source_code' => $question['snippet_source_code'],
        'tags' => $question['tags'],
        'likes' => $question['likes'],
        'dislikes' => $question['dislikes'],
        'num_stars' => $question['num_stars'],
        'dont_know' => $question['dont_know'],
        'comments' => $question['comments'],
        'num_warm_up_questions' => $this->num_warm_up_questions,
        'warm_up_question' => $question['warm_up_question'],
        'total_num_questions' => $this->num_questions-1,
        'question_type' => $this->RATE_QUESTION_STR
      ));
    } else if ($question['question_type'] == $this->FORCED_CHOICE_QUESTION_STR) {
      $this->render($this->FORCED_CHOICE_QUESTION_STR . '/question', array(
        'question_index' => $question_index,
        'progress' => Session::get('progress'),
        'tags' => $question['tags'],
        'snippet_a_id' => $question['snippet_a_id'],
        'snippet_a_source_code' => $question['snippet_a_source_code'],
        'snippet_a_likes' => $question['snippet_a_likes'],
        'snippet_a_dislikes' => $question['snippet_a_dislikes'],
        'snippet_b_id' => $question['snippet_b_id'],
        'snippet_b_source_code' => $question['snippet_b_source_code'],
        'snippet_b_likes' => $question['snippet_b_likes'],
        'snippet_b_dislikes' => $question['snippet_b_dislikes'],
        'chosen_snippet_id' => $question['chosen_snippet_id'],
        'dont_know' => $question['dont_know'],
        'comments' => $question['comments'],
        'num_warm_up_questions' => $this->num_warm_up_questions,
        'warm_up_question' => $question['warm_up_question'],
        'total_num_questions' => $this->num_questions-1,
        'question_type' => $this->FORCED_CHOICE_QUESTION_STR
      ));
    }
  }

  /**
   *
   */
  private function checkAnswer($question_index) {
    $questions = Session::get('questions');
    $question = $questions[$question_index];

    // tracking time
    $question['time_to_answer'] = $_POST['time_to_answer'];

    // no answer?
    $dont_know = isset($_POST['dont_know_textarea']) ? preg_replace('/\s+/', ' ', $_POST['dont_know_textarea']) : '';
    if (str_word_count($dont_know) == 0) {
      $dont_know = '';
    }

    $question['comments'] = isset($_POST['comments_textarea']) ? preg_replace('/\s+/', ' ', $_POST['comments_textarea']) : '';

    $is_it_complete = true;
    if ($dont_know != '') {
      $question['dont_know'] = $dont_know;
    } else {
      if ($question['question_type'] == $this->RATE_QUESTION_STR) {
        $question['likes'] = ($_POST['like-container'] == "" ? array() : explode(',', $_POST['like-container']));
        $question['dislikes'] = ($_POST['dislike-container'] == "" ? array() : explode(',', $_POST['dislike-container']));
        $question['num_stars'] = $_POST['star-rating'];

        if ($question['num_stars'] == 0 && count($question['likes']) == 0 && count($question['dislikes']) == 0) {
          Session::set('s_errors', array(INCOMPLETE_ANSWER));
          $is_it_complete = false;
        } else if ($question['num_stars'] == 0 && (count($question['likes']) > 0 || count($question['dislikes']) > 0)) {
          Session::set('s_errors', array(INCOMPLETE_SURVEY_RATE_MISSING_RATE));
          $is_it_complete = false;
        } else if ($question['num_stars'] > 0 && (count($question['likes']) == 0 && count($question['dislikes']) == 0)) {
          Session::set('s_errors', array(INCOMPLETE_SURVEY_RATE_MISSING_TAGS));
          $is_it_complete = false;
        }
      } else if ($question['question_type'] == $this->FORCED_CHOICE_QUESTION_STR) {
        $question['snippet_a_likes'] = ($_POST['test_case_a_like-container'] == "" ? array() : explode(',', $_POST['test_case_a_like-container']));
        $question['snippet_a_dislikes'] = ($_POST['test_case_a_dislike-container'] == "" ? array() : explode(',', $_POST['test_case_a_dislike-container']));
        $question['snippet_b_likes'] = ($_POST['test_case_b_like-container'] == "" ? array() : explode(',', $_POST['test_case_b_like-container']));
        $question['snippet_b_dislikes'] = ($_POST['test_case_b_dislike-container'] == "" ? array() : explode(',', $_POST['test_case_b_dislike-container']));
        $question['chosen_snippet_id'] = $_POST['chosen_snippet_id'];

        if ($question['chosen_snippet_id'] == "" &&
              count($question['snippet_a_likes']) == 0 && count($question['snippet_a_dislikes']) == 0 &&
              count($question['snippet_b_likes']) == 0 && count($question['snippet_b_dislikes']) == 0) {
          Session::set('s_errors', array(INCOMPLETE_ANSWER));
          $is_it_complete = false;
        } else if ($question['chosen_snippet_id'] == "") {
          Session::set('s_errors', array(INCOMPLETE_SURVEY_FORCED_CHOICE_MISSING_SELECTION));
          $is_it_complete = false;
        } else if ($question['chosen_snippet_id'] != "" &&
              (count($question['snippet_a_likes']) == 0 && count($question['snippet_a_dislikes']) == 0)) {
          Session::set('s_errors', array(INCOMPLETE_SURVEY_FORCED_CHOICE_MISSING_TAGS_OF_A));
          $is_it_complete = false;
        } else if ($question['chosen_snippet_id'] != "" &&
              (count($question['snippet_b_likes']) == 0 && count($question['snippet_b_dislikes']) == 0)) {
          Session::set('s_errors', array(INCOMPLETE_SURVEY_FORCED_CHOICE_MISSING_TAGS_OF_B));
          $is_it_complete = false;
        }
      }
    }

    // update questions
    $questions[$question_index] = $question;
    Session::set('questions', $questions);

    return $is_it_complete;
  }

  /**
   *
   */
  private function progressBar() {
    $questions = Session::get('questions');

    $how_many_answered_so_far = 0;
    foreach ($questions as $question) {
      if ($question['warm_up_question']) {
        # warm-up questions should not update the progressBar
        continue;
      }

      if ($question['dont_know'] != '') {
        $how_many_answered_so_far++;
      } else {
        if ($question['question_type'] == $this->RATE_QUESTION_STR) {
          if (count($question['likes']) > 0 || count($question['dislikes']) > 0) {
            $how_many_answered_so_far++;
          }
        } else if ($question['question_type'] == $this->FORCED_CHOICE_QUESTION_STR) {
          if ((count($question['snippet_a_likes']) > 0 || count($question['snippet_a_dislikes']) > 0)
              && (count($question['snippet_b_likes']) > 0 || count($question['snippet_b_dislikes']) > 0)
              && $question['chosen_snippet_id'] != "") {
            $how_many_answered_so_far++;
          }
        }
      }
    }

    $progress = $how_many_answered_so_far * round(100.0 / $this->num_questions);
    Session::set('progress', $progress);
  }

  /**
   *
   */
  private function submitToDB($question_index) {
    $user_id = Session::get('user_id');
    if (!isset($user_id)) {
      // this function is private and it is not likely that someone
      // would be able to access it without a user_id, but just in
      // case
      header('location: ' . URL);
      return false;
    }

    $questions = Session::get('questions');
    $question = $questions[$question_index];

    if ($question['warm_up_question']) {
      # no need to keep track of answers of warm-up questions as they
      # are only to practice/demonstration
      return true;
    }

    // get survey model to access DB functions
    $survey_model = $this->loadModel('survey');

    if ($question['question_type'] == $this->RATE_QUESTION_STR) {
      if (! $survey_model->createRateAnswer(
        // Answer
        $question['question_type'], $user_id, $question['time_to_answer'], $question['dont_know'], $question['comments'],
        // Rate
        $question['num_stars'],
        // AnswerSnipper
        $question['snippet_id'],
        // Tags
        $question['likes'], $question['dislikes']
      )) {
        Session::set('s_errors', array("It was not possible to create a 'rate' answer!"));

        var_dump(Session::get('s_errors'));
        print("<br />");
        $this->prettyPrintQuestion($question);
        die(); // TODO are you sure? how about writing everything to a file and send it to me by email?

        return false;
      }
    } else if ($question['question_type'] == $this->FORCED_CHOICE_QUESTION_STR) {
      if (! $survey_model->createForcedChoiceAnswer(
        // Answer
        $question['question_type'], $user_id, $question['time_to_answer'], $question['dont_know'], $question['comments'],
        // Chosen snippet
        $question['chosen_snippet_id'],
        // AnswerSnipper
        $question['snippet_a_id'], $question['snippet_b_id'],
        // Tags
        $question['snippet_a_likes'], $question['snippet_a_dislikes'], $question['snippet_b_likes'], $question['snippet_b_dislikes']
      )) {
        Session::set('s_errors', array("It was not possible to create a 'forced_choice' answer!"));

        var_dump(Session::get('s_errors'));
        print("<br />");
        $this->prettyPrintQuestion($question);
        die(); // TODO are you sure? how about writing everything to a file and send it to me by email?

        return false;
      }
    }

    return true;
  }

  /**
   *
   */
  private function prettyPrintQuestions($question) {
    if ($question['question_type'] == $this->RATE_QUESTION_STR) {
      print("<table style=\"width:100%;\">");
        print("<tr>");
          print("<th>question_type</th>");
          print("<th>snippet_id</th>");
          print("<th>time_to_answer</th>");
          print("<th>num_stars</th>");
          print("<th>likes</th>");
          print("<th>dislikes</th>");
          print("<th>don't know</th>");
        print("</tr>");
        print("<tr>");
          print("<td>" . $question['question_type'] . "</td>");
          print("<td>" . $question['snippet_id'] . "</td>");
          print("<td>" . $question['time_to_answer'] . "</td>");
          print("<td>" . $question['num_stars'] . "</td>");
          print("<td>" . implode(',', $question['likes']) . "</td>");
          print("<td>" . implode(',', $question['dislikes']) . "</td>");
          print("<td>" . $question['dont_know'] . "</td>");
          print("<td>" . $question['comments'] . "</td>");
        print("</tr>");
      print("</table>");
      print("<br />");
    } else if ($question['question_type'] == $this->FORCED_CHOICE_QUESTION_STR) {
      print("<table style=\"width:100%;\">");
        print("<tr>");
          print("<th>question_type</th>");
          print("<th>snippet_a_id</th>");
          print("<th>snippet_b_id</th>");
          print("<th>time_to_answer</th>");
          print("<th>chosen_snippet_id</th>");
          print("<th>snippet_a_likes</th>");
          print("<th>snippet_a_dislikes</th>");
          print("<th>snippet_b_likes</th>");
          print("<th>snippet_b_dislikes</th>");
          print("<th>don't know</th>");
        print("</tr>");
        print("<tr>");
          print("<td>" . $question['question_type'] . "</td>");
          print("<td>" . $question['snippet_a_id'] . "</td>");
          print("<td>" . $question['snippet_b_id'] . "</td>");
          print("<td>" . $question['time_to_answer'] . "</td>");
          print("<td>" . $question['chosen_snippet_id'] . "</td>");
          print("<td>" . implode(',', $question['snippet_a_likes']) . "</td>");
          print("<td>" . implode(',', $question['snippet_a_dislikes']) . "</td>");
          print("<td>" . implode(',', $question['snippet_b_likes']) . "</td>");
          print("<td>" . implode(',', $question['snippet_b_dislikes']) . "</td>");
          print("<td>" . $question['dont_know'] . "</td>");
          print("<td>" . $question['comments'] . "</td>");
        print("</tr>");
      print("</table>");
      print("<br />");
    }
  }

  /**
   *
   */
  public function thanks() {
    // if there is no user_id, user should not have access to 'thanks' option
    $user_id = Session::get('user_id');
    if (!isset($user_id)) {
      header('location: ' . URL);
      return;
    }

    $completed = Session::get('completed');
    if (!isset($completed)) {
      header('location: ' . URL . 'survey/question/' . Session::get('question_index'));
      return;
    }

    $token = Session::get('token');
    $prolific_token = Session::get('prolific_token');

    // clean session
    Session::destroy();

    // say thanks and show the token
    $this->render('survey/thanks', array(
      'token' => $token,
      'prolific_token' => $prolific_token
    ));
  }
}

?>
