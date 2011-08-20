# Field: Hierarchy

A new field that lets you link your entries to each other with an intelligent hierarchy interface.

__Version:__ 0.3  
__Date:__ 9 June 2011  
__Requirements:__ Symphony 2.2  
__Author:__ Rowan Lewis <me@rowanlewis.com>  
__GitHub Repository:__ <http://github.com/rowan-lewis/hierarchy_field>  


## Installation

1. Copy the `hierarchy_ui` and `hierarchy_field` folders to your `extensions` folder.

2. Enable both the "Hierarchy UI" and "Field: Hierarchy" extensions by choosing "Enable" from the with-selected menu and clicking "Apply".

3. Add a Text Input, Textarea or Text Box field to your section, this will become the "Title" of your entry.

4. Add a Hierarchy field to your section.


## Usage

The Hierarchy interface may be a little confusing; tis is the first thing you'll see:

![An empty Hierarchy field][usage-step-one]

The first button is the clear button, if there where any items in the Hierarchy field it would remove them, the second button is the add item button. If you don't have any entries in your section, clicking the add item button won't do anything.

After clicking the add item button you will see this:

![Choosing a parent item][usage-step-two]

Clicking on one of the drop down options will add it to the end your Hierarchy field:

![A Hierarchy field containing one item][usage-step-three]

If you click on the add item button again, it will give you a list of entries aleady linked to the last item in the Hierarchy field.


[usage-step-one]: https://github.com/rowan-lewis/hierarchy_field/raw/master/docs/usage-step-one.png
[usage-step-two]: https://github.com/rowan-lewis/hierarchy_field/raw/master/docs/usage-step-two.png
[usage-step-three]: https://github.com/rowan-lewis/hierarchy_field/raw/master/docs/usage-step-three.png