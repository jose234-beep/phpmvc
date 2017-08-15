/**
 * WebTour JS
 */

// Instance of the 'rate' tour
var rate_tour = new Tour({
  name: "rating_tour",
  steps: [
  {
    element: "#test_case_panel",
    placement: "top",
    title: "Source Code Snippet",
    content: "On the left side of the webpage is the source code of a\
    unit test case."
  },
  {
    element: "#rating_panel",
    placement: "left",
    title: "Rating",
    content: "And on the right side you can rate each test case from\
    1 to 5 stars. Give 1 star to test cases you do not like at all, and\
    5 stars to test cases you like very much. This should just capture\
    your first impression of the test case, not how well you understand\
    the test case."
  },
  {
    element: "#tags_panel",
    placement: "left",
    title: "Tags",
    content: "Use any set of tags to justify why do you like / dislike\
    the test case provided. Tags from this box can be drag & drop into\
     the below 'Like' or 'Dislike' box."
  },
  /*{
    element: "#likes_panel",
    placement: "left",
    title: "Likes",
    content: "..."
  },
  {
    element: "#dislikes_panel",
    placement: "left",
    title: "Dislikes",
    content: "..."
  },*/
  {
    element: "#comments_textarea",
    placement: "top",
    title: "Extra Comments (optional)",
    content: "Use this textbox to provide additional comments you might have."
  },
  {
    element: "#dont_know_button",
    placement: "top",
    title: "Provide an Explanation",
    content: "In case you are not able to assess how much you like the\
    test case, use the 'Skip' button and provide an explanation on why."
  },
  {
    element: "#next_button",
    placement: "top",
    title: "Move to Next Question",
    content: "Once you have rated the test case provided or chosen to\
    skip it, select 'Next' to go to the next question.",
    // Override template for the last step, i.e., no need to show "Next",
    // but "End tour" should be shown
    template: "<div class='popover tour'>\
              <div class='arrow'></div>\
              <h3 class='popover-title'></h3>\
              <div class='popover-content'></div>\
              <div class='popover-navigation'>\
                <div class='btn-group'>\
                  <button class='btn btn-sm btn-default' data-role='prev'>« Prev</button>\
                </div>\
                <button class='btn btn-sm btn-default' data-role='end'>End tour</button>\
              </div>\
            </div>",
  }],
  // Disable storage persistence, i.e., the tour starts from beginning
  // every time the page is loaded
  storage: false,
  // Show a dark backdrop behind the popover and its element,
  // highlighting the current step.
  backdrop: true,
  template: "<div class='popover tour'>\
              <div class='arrow'></div>\
              <h3 class='popover-title'></h3>\
              <div class='popover-content'></div>\
              <div class='popover-navigation'>\
                <div class='btn-group'>\
                  <button class='btn btn-sm btn-default' data-role='prev'>« Prev</button>\
                  <button class='btn btn-sm btn-default' data-role='next'>Next »</button>\
                </div>\
                <!--<button class='btn btn-sm btn-default' data-role='end'>End tour</button>-->\
              </div>\
            </div>",
  onEnd: function (tour) {
    // scroll to the top
    $("html, body").animate({ scrollTop: 0 }, "slow");
    // e.g. redirect to another URL:
    // document.location.href = '/url/' + userId;
  }
});

// Instance of the 'forced_choice' tour
var forced_choice_tour = new Tour({
  name: "forced_choice_tour",
  steps: [
  {
    element: "#test_snippets",
    placement: "top",
    title: "Test Cases",
    content: "Each question is composed by two unit test cases, select\
    the one that you like most by selecting 'Test A' or 'Test B'."
  },
  {
    element: "#tags_panel",
    placement: "top",
    title: "Tags",
    content: "Use any set of tags to justify why do like/dislike 'Test A'\
    more/less than 'Test B' and vice-versa. Tags from this box can be\
    drag & drop into the below 'Like' or 'Dislike' boxes."
  },
  /*{
    element: "#likes_panel",
    placement: "left",
    title: "Likes",
    content: "..."
  },
  {
    element: "#dislikes_panel",
    placement: "left",
    title: "Dislikes",
    content: "..."
  },*/
  {
    element: "#comments_textarea",
    placement: "top",
    title: "Extra Comments (optional)",
    content: "Use this textbox to provide additional comments you might have."
  },
  {
    element: "#dont_know_button",
    placement: "top",
    title: "Provide an Explanation",
    content: "In case you are not able to assess how much you like the\
    test case, use the 'Skip' button and provide an explanation on why."
  },
  {
    element: "#next_button",
    placement: "top",
    title: "Move to Next Question",
    content: "Once you have answered the question or chosen to skip it,\
    select 'Next' to go to the next question.",
    // Override template for the last step, i.e., no need to show "Next",
    // but "End tour" should be shown
    template: "<div class='popover tour'>\
              <div class='arrow'></div>\
              <h3 class='popover-title'></h3>\
              <div class='popover-content'></div>\
              <div class='popover-navigation'>\
                <div class='btn-group'>\
                  <button class='btn btn-sm btn-default' data-role='prev'>« Prev</button>\
                </div>\
                <button class='btn btn-sm btn-default' data-role='end'>End tour</button>\
              </div>\
            </div>",
  }],
  // Disable storage persistence, i.e., the tour starts from beginning
  // every time the page is loaded
  storage: false,
  // Show a dark backdrop behind the popover and its element,
  // highlighting the current step.
  backdrop: true,
  template: "<div class='popover tour'>\
              <div class='arrow'></div>\
              <h3 class='popover-title'></h3>\
              <div class='popover-content'></div>\
              <div class='popover-navigation'>\
                <div class='btn-group'>\
                  <button class='btn btn-sm btn-default' data-role='prev'>« Prev</button>\
                  <button class='btn btn-sm btn-default' data-role='next'>Next »</button>\
                </div>\
                <!--<button class='btn btn-sm btn-default' data-role='end'>End tour</button>-->\
              </div>\
            </div>",
  onEnd: function (tour) {
    // scroll to the top
    $("html, body").animate({ scrollTop: 0 }, "slow");
    // e.g. redirect to another URL:
    // document.location.href = '/url/' + userId;
  }
});

