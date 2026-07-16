# cf7-field-validator
rudimentory plugin for cf7 that adds a custom validation in the ui

![admin menu](https://github.com/alexKov24/cf7-field-validator/blob/main/admin-menu.png)

## Why?
default cf7 field checkers are simple and brittle. You can still use them, but if you want an easy way to expand on them - this plugin is for you.

## How does it work?
3 inputs => field name + condition + error message
for a form to submit the field name must satisfy a condition 
otherwise an error message will be shown.

### basic example

we can prevent temporary email providers like mailinator from submitting 

|field|negation|operator|value|error message|
|-|-|-|-|-|
|your-email| not | contains | "mailinator, temp-mail, maildrop"| please use static email address|

the email "gibberish@mailinator.com" will match the value and trigger an error

### multiple conditions

Sometimes you want to have multiple conditions, for example to limit phone length:

|field|negation|operator|value|error message|
|-|-|-|-|-|
|your-tel| not | longer than | 5 | please check your phone number |
|your-tel| not | shorter than | 9 | please chekc your phone muber |


### glbal vs local 

The rules are split into **global** - define in the plugin settings, and **local** define on the cf7 instance. 

Just like the name suggests global rules apply to all forms, locals apply only to one you edit. You can also opt out of a global rule set if you so wish using the checkbox


## Install
Download the plugin in releases section. Go to WP->Plugins->Upload the zip file.
You can also directly place the release directory in wp-content/plugins.

dont forget to activate!

## Extendability

the `validator_panel_html` and `render_rule_row` provide the essential visual to help set the correct values by the user, you can expand them to include new items.

the `validate_fields` includes, as the name suggests, the validation of each rule. expand this to create custom validation methods.
