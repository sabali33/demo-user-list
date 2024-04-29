A simple WordPress plugins that allows for you to create users and list them in a shortcode. 
To create a user first display the form through the shortcode: [dul_user_form]. This shortcode receives the following arguments:
- `page` that specifies the page to query user
- `per_page` that tells how many users to query at a time. It has default of 10
- `orderby` that specifies the field by which queried users should be order. It has a default of created_at
- `order` indicates whether the results should be ordered in ascending or descending order.
- `search` a string that is used to search for user names that contain a term.

By the arguments above users can be queried through the REST endpoint: `wp-json/dul-demo-user/v1/users/`
