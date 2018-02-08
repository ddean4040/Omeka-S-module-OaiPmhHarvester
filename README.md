OAI-PMH Harvester (module for Omeka S)
===============================

[OAI-PMH Harvester] is a module for [Omeka S] built on the [CSV Import] module. It allows operators of an Omeka S install to harvest records via OAI-PMH. This module is a proof of concept with limited functionality, lots of unused code, and possibly many hidden bugs.

A set is created in Omeka S for each combination of OAI-PMH repository hostname and set name harvested.

Please note the following limitations of the current version:
 - Only OAI-DC is supported as a source format
 - An Omeka set will be created for each set harvested -- this is not configurable
 - Duplicate items will be created by repeated harvests
 - There is no auto-mapping of fields and mapping is not stored
 - Harvest stats are not populated
 - Undoing a harvest removes the harvested items but does not delete the set

Installation
------------

See general end user documentation for [Installing a module](http://dev.omeka.org/docs/s/user-manual/modules/#installing-modules)

Harvesting items
----------------

Select New Harvest from the OAI-PMH Harvester menu under Modules

Updating and deleting items
---------------------------


Warning
-------

Use it at your own risk.

It’s always recommended to backup your files and your databases and to check your archives regularly so you can roll back if needed.

Troubleshooting
---------------

See online issues on the [Omeka forum].

License
-------

This plugin is published under [GNU/GPL v3].

This program is free software; you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation; either version 3 of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.

You should have received a copy of the GNU General Public License along with this program; if not, write to the Free Software Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.

Contact
-------

Current maintainers:


Maintainers of the [CSV Import] module:
* Roy Rosenzweig Center for History and New Media
* Daniel Berthereau (see [Daniel-KM] on GitHub)

Copyright
---------

* Portions Copyright Roy Rosenzweig Center for History and New Media, 2015-2017
* Portions Copyright Daniel Berthereau, 2017

[OAI-PMH Harvester]: https://github.com/
[CSV Import]: https://github.com/Omeka-s-modules/OaiPmhHarvester
[Omeka S]: https://omeka.org/s
[Omeka forum]: https://forum.omeka.org/c/omeka-s/modules
[GNU/GPL v3]: https://www.gnu.org/licenses/gpl-3.0.html
[Daniel-KM]: https://github.com/Daniel-KM "Daniel Berthereau"

