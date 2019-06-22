# moodle-filter_h5p
Adds a Filter to Moodle to automatically embed an H5P-content in textfields.

This filter adds the possibility to get h5p content being automatically embedded in textfields based on the activityname.

In order to use this filter you have to activate it after installation and ensure that it is listed above the "link activites"-filter. If you have created an interactive content using h5p you can embed it anywhere in the course you like by using the syntax {h5p:activity name}.

Example:

1. Create an interactive Video with the name "My interactive Video"
2. (optional) Set the visibility to "available but not shown on course page"
3. Enter in any textfield the following pattern {h5p:My interactive Video}
4. It will be embedded automatically and the results will be graded in your course.

Please note, that the activity name is case sensitive!
