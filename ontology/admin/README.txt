These are structWSF administrator scripts. Each of these scripts have their own specificities, so make sure you read 
each of them below.

====================
analyzeRegisters.php
====================

This script is used to see if the OWLAPI instance is up and running. Also, it tells if the sessions threads are 
currently in use, and what are their status.

------------------------
Security Considerations:
------------------------

Make sure that this script is only accessible to the administrators of your server. This can be done by restricting
access to it to a certain IP address, in Apache2. You can also move it in another location.

Otherwise, make sure to delete it from the file server if you can't restrict (or don't want to) its access.


===========
destroy.php
===========

This script is used to destroy all the running OWLAPI instances sessions threads. This script can be used to clean
an instance when doing some testing or such.

------------------------
Security Considerations:
------------------------

Make sure that this script is only accessible to the administrators of your server. This can be done by restricting
access to it to a certain IP address, in Apache2. You can also move it in another location.

Otherwise, make sure to delete it from the file server if you can't restrict (or don't want to) its access.

