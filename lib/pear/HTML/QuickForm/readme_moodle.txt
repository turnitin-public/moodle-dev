Description of the process to import/upgrade HTML Quickforms
Last updated 11 Jul 2019.

QuickForms has been directly modified a number of times. As such, the
general upgrade process involves replacing the third party library
and re-applying the modifications.

The steps are as follows:

# Get the latest QuickForm from git:
  https://github.com/pear/HTML_QuickForm

# Replace the contents of lib/pear/HTML/QuickForm/ directory with new contents.

# Replace lib/pear/HTML/QuickForm.php with new file.

# Fix line endings in imported files, where applicable.

# Reapply the fix for object instantiation. Originally patched in MDL-19698, but refer to the commit in MDL-63070.

# Reapply the fix for php7 constructors. Originally patched in MDL-52081, but refer to the commit in MDL-63070.

# Reapply the fix for non-static method called statically. Originally patched in MDL-32405 and MDL-41908, but refer
  to the single commit covering both of these in MDL-63070.

# Reapply the fix for multibyte string support in Rule/Range. Originally patched in MDL-40267, but refer to the commit
  in MDL-63070.

# Reapply the fix for checkbox and radio id generation. This removes the generateId() calls from the constructors of
  lib/pear/HTML/QuickForm/checkbox.php and lib/pear/HTML/QuickForm/radio.php elements. Originally patched as part of
  MDL-30168 but refer to the single commit in MDL-63070. 2 lines of change only.

# Reapply the fix for stripslashes. This removes the use of stripslashes. Originally patched in MDL-24058, but refer to
  the commit in MDL-63070.

# Reapply the fix for self contained form javascript. Originally patched in MDL-52826 but refer to the commit in
  MDL-63070.

# Reapply the fix handling the replacement of create_function (deprecated). Originally patched in MDL-60281, but that
  solution used eval, so please refer to the commit in MDL-63070 which uses an anonymous function.

# Reapply the changes to the rule registry, enabling the autocomplete element. This was originally made in MDL-51247,
  but refer to the commit in MDL-63070.

# Reapply the changes allowing optional randomised element ids. Originally made in MDL-65217, but refer to the commit in
  MDL-63070.

# Reapply the fix for advanced checkbox validation in the rule registry. Originally patched in MDL-65505, but refer to
  the commit in MDL-63070.

# Update this document, where applicable.


Previous modifications to the library which DO NOT need to be repplied:
MDL-50484:
    toHTML() related, and since Bootstrap 4 uses templates to render elements, this no longer needs to be reapplied.
MDL-31469:
    toHTML() related, and since Bootstrap 4 uses templates to render elements, this no longer needs to be reapplied.
MDL-56110:
    The notice in createElement() was meant to help plugins calling this statically, specifically when they were moving
    to 7.1. 7.1 is now a requirement, so this does not need to be applied.
