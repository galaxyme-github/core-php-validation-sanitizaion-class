# Core PHP class for Validation & Sanitization

This core PHP class is useful to validate and sanitize an HTML form fields($_POST, $_GET).

## Usage
```php 
require_once('DataGlobal.php');
```

## Use
```php
    $DataGlobal = new DataGlobal();
    $DataGlobal -> set_rules('username', 'Username', 'required|min_length[3]');
    $DataGlobal -> set_rules('pwd', 'Password', 'required|min_length[8]|max_langth[30]');
    $DataGlobal -> set_rules('pwd_confirm', 'Confirm Password', 'required|min_length[8]|max_langth[30]|matches[pwd]');
    $DataGlobal -> set_rules('email', 'Email', 'required|valid_email');
    $DataGlobal -> set_rules('gender', 'Gender', 'required');
    $DataGlobal -> set_rules('phone_number', 'Phone number', 'required|phone', array('required' => 'Please fill phone number field'));
    if ($DataGlobal -> validate())
    {
        echo 'Validation Successful!';
    }
    else
    {
        echo $DataGlobal -> error_array();
        
        $username = $DataGlobal -> sunitize('username', 'string|trim'); 
        // or $DataGlobal -> sunitize('username', 'string|prevent_sql_injection', 
        //                      array('hostname' => 'localhost:8080', 'username' => 'root', 'password' => ''));
        $password = $DataGlobal -> sunitize('pwd', 'do_hash', 'sha1') // @param sha1 : hash type
        $email = $DataGlobal -> sunitize('email', 'email');
    }
```
************************ set rules **************************
 ```php   
    $DataGlobal -> set_rules('username', 'Username', 'requried') -> validate(); 
 ```

************************ set rules **************************
```php   
    $param = array(
        array(
            'field' => 'username',
            'label' => 'Username',
            'rules' => 'required'
        ),
        array(
            'field' => 'password',
            'label' => 'Password',
            'rules' => 'required|min_langth[8]',
            'errors' => array(
                'min_langth' => 'The field must be at least 8 characters in length.'
            )
        ),
        array(
            'field' => 'email',
            'label' => 'Email',
            'rules' => 'required'
        )
    );
    $DataGlobal -> set_rules($param) -> validate();
```

## method: set_rules()
You could set as many validation rules as you need for a given field and cascading them in order.
If you arranged rules like 'required|valid_email', first required checking is called, then valid_email checking will be worked.
# ->set_rules('field', 'label', 'rules', 'errors')
field - $_POST['field'] or $_GET['field']
This method takes three parameters at least.
1. The field name - the exact name you've given the form field ($_POST['filed'] or $_GET['field']).
2. A "human" name for this field, which will be inserted into the error message. For example, if your field is named "user"
   you might give it a human name of "Username".
3. The validation rules for this form field.
4. (optional)Set custom error messages on any rules given for current field. If not provided will use the default one.

# ! Note 
You could add or change error message languages in ErrorLang.php.

# ex:
$datavalid = new DataGlobal();
# single rule
$datavalid -> set_rules('username', 'Username', 'required'); 
$datavalid -> set_rules('password', 'Password', 'required');
# multiple rules
$datavalid -> set_rules('passconf', 'Password Confirmation', 'required|matches[password]');

## Important methods
| Method       | Parameter                       | Description                             | Example   |
|--------------|---------------------------------|-----------------------------------------|-----------|
| set_rules    | $field, $label, $rules, $errors | Set rules as array                      | 1)        |
| validate     |                                 | Return bool                             | 2)        |
| sanitize     | $field, $type, $data_for        | Return sanitized variable               | 3)        |
| error        | $field, $prefix, $suffix        | Gets the error message associated       | 4)        |
|              |                                 | with a particular field                 |           |
| error_array  | $pattern                        | Returns the error messages as an array  | 5)        |
| error_string | $prefix, $suffix                | Returns the error messages as a string, | 6)        |
|              |                                 |  wrapped in the error delimiters        |           |


1) set_rules('username', 'Username', 'required', array('required' => 'custom error message')
2) set_rules(...)->validate() or ->set_rules(...); ->validate();
3) sanitize('username', 'filter_special_chars', 'sha1')
4) ->error('username', '<div class="error">', '</div>)
5) ->error_array()
6) ->error_string('<p class="error">', '</p>')

## Rule Reference
*** The following is a list of all the native rules that are available to use ***

| Rule                  | Parameter | Description                                 | Example                   |
|-----------------------|-----------|---------------------------------------------|---------------------------|
| required              | No        | Returns FALSE if the form element is empty. |                           |
| matches               | Yes       | Returns FALSE if the form element does not  | matches[form_item]        |
|                       |           | match the one in the parameter.             |                           |
| regex_match           | Yes       | Returns FALSE if the form element does not  | regex_match[/regex/]      |
|                       |           | match the regular expression.               |                           |
| min_length            | Yes       | Returns FALSE if the form element is shorter| min_length[3]             |
|                       |           | than the parameter value.                   |                           |
| max_length            | Yes       | Returns FLASE if the form element is longer | max_length[12]            |
|                       |           | than the parameter value.                   |                           |
| exact_langth          | Yes       | Returns FALSE if the form element is not    | exact_length[8]           |
|                       |           | exactly the parameter value.                |                           |
| greater_than          | Yes       | Returns FALSE if the form element is less   | greater_than[8]           |
|                       |           | than or equal to the parameter value or not |                           |
|                       |           | numeric.                                    |                           |
| greater_than_equal_to | Yes       | Returns FALSE if the form element is less   | greater_than_equal_to[8]  |
|                       |           | than the parameter value, or not numeric.   |                           |
| less_than             | Yes       | Returns FALSE if the form element is greater| less_than[8]              |
|                       |           | than or equal to the parameter value or not |                           |
|                       |           | numeric.                                    |                           |
| less_than_equal_to    | Yes       | Returns FALSE if the form element is greator| less_than_equal_to[8]     |
|                       |           | than the parameter value, or not numeric.   |                           |
| in_list               | Yes       | Returns FALSE if the form element is not    | in_list[red,blue,green]   |
|                       |           | within a predetermined list.                |                           |
| alpha                 | No        | Returns FALSE if the form element contains  |                           |
|                       |           | anything other than alpha-numeric characters.                           |
| alpha_numeric         | No        | Returns FALSE if the form element contains  |                           |
|                       |           | anything other than alpha-numeric characters.                           |
| alpha_numeric_spaces  | No        | Returns FALSE if the form element contains  |                           |
|                       |           | anything other than alpha-numeric characters|                           |
|                       |           | or spaces. Should be used after trim to avoid                           |
|                       |           | spaces at the beginning or end.             |                           |
| alpha_dash            | No        | Return FALSE if the form element contains   |                           |
|                       |           | anything other than alpha-numeric characters,                           |
|                       |           | underscores or dashes.                      |                           |
| numeric               | No        | Returns FALSE if the form element contains  |                           |
|                       |           | anything other than numeric characters.     |                           |
| integer               | No        | Returns FALSE if the form element contains  |                           |
|                       |           | anything other than an integer.             |                           |
| decimal               | No        | Returns FALSE if the form element contains  |                           |
|                       |           | anything other than a decimal number.       |                           |
| is_natural            | No        | Returns FALSE if the form element contains  |                           |
|                       |           | anything that a natural number: 0, 1, 2, etc|                           |
| is_natural_no_zero    | No        | Returns FALSE if the form element contains  |                           |
|                       |           | anything other than a natural number, but   |                           |
|                       |           | bot zero: 1, 2, 3, etc.                     |                           |
| valid_url             | No        | Returns FALSE if the form element does not  |                           |
|                       |           | contains a valid URL.                       |                           |
| valid_email           | No        | Returns FLASE if the form element does not  |                           |
|                       |           | contain a valid email address.              |                           |
| valid_emails          | No        | Returns FALSE if any value provided in a comma                          |
|                       |           | separated list is not a valid email.        |                           |
| valid_ip              | No        | Returns FALSE if the supplied IP address is |                           |
|                       |           | not valid. Accepts an optional parameter of |                           |
|                       |           | 'ipv4' or 'ipv6' to specify an IP format.   |                           |
| valid_base64          | No        | Returns FALSE if the supplied contains anything                         |
|                       |           | other than valid Base64 charaters.          |                           |
| phone                 | No        | Returns FLASE if the form element contains  |                           |
|                       |           | anything other than only a+ sign, number and space.                     |
| special_chars         | Yes       | Returns FALSE if the form element contains  | special_chars[<>"'%]      |
|                       |           | over one of the passed special chars.       |                           |
| allowed_chars         | Yes       | Returns FLASE if the form element value is  | allowed_chars[abcde123$]  |
|                       |           | not consist of only passed chars.           |                           |

## Sanitize Method


# format: 
```php
->sanitize($field, $type ='', $data_for = '')
```

# ex: 
```php
->sanitize('username', 'string|trim|filter_special_chars');
```

# ! Notice
If you would like to pass prevent_sql_injection or do_hash, you should remarkable $data_for.

- prevent_sql_injection
```php
$data_for = array('hostname' => 'your hostname',
                  'username' => 'your username',
                  'password' => 'your password'
            );
```

- do_hash
```php
$data_for = 'ash1';
```

## Sanitization Type Reference
| Type                         | Description                                                        |
|------------------------------|--------------------------------------------------------------------|
| url                          | Removes all illegal character from URL                             |
| int                          | Removes all characters except digits and + - signs                 |
| float                        | Remove all characters, except digits, +- signs, and optionally     |
| email                        | Removes all illegal characters from an e-mail address              |
| string                       | Removes tags/special characters from a string                      |
| to_html_entities             | trim string, Convert some characters to HTML entities,             |
|                              | Encodes double and single quotes                                   |
| to_html_entities_plain       | trim string, Convert some characters to HTML entities,             |
|                              | plain text, Does not encode any quotes                             |
| upper                        | trim string, upper case first word                                 |
| ucfirst                      | trim string, upper case first word                                 |
| lower                        | trim string, lower case words                                      |
| urle                         | trim string, url encoded                                           |
| trim_urle                    | trim string, url decoded                                           |
| filter_special_chars         | Filter HTML-escapes special characters, convert to html entities   |
| magic_quotes                 | This is safe in a database query as adding slash                   |
|                              | front of predefined characters.                                    |
| remove_backslashes           | Remove the backslash.                                              |
| remove_html_tags             | Strip the string from HTML tags                                    |
| trim                         | Remove white space from both sides of a string                     |
| remove_space                 | Remove all white space from string.                                |
| encode_php_tags              | Convert PHP tags to entities. '<?', '?>' -> '&lt;?', '?&gt;'       |
| prep_for_form                | Special characters are converted.                                  |
|                              | "'", '"', '<', '>' -> '&#39;', '&quot;', '&lt;', '&gt;'            |
| do_hash                      | Hash encode a string                                               |
| prevent_sql_injection        | Prevent SQL injection                                              |

# ! Notice
in prevent_sql_injection, you may pass or not $data_for
if you would like to prevent slq injection more powerfully, you should pass array parameter to $data_for variable.




## 9/12/2020 

## End readme for valdation&sanitization class