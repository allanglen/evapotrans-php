evapotrans-php
=========

A PHP implementation of the United Nations Food and Agriculture Organization (FAO) Penman-Monteith evapotranspiration equation.

For more information see:
* http://en.wikipedia.org/wiki/Penman%E2%80%93Monteith_equation
* http://www.fao.org/docrep/X0490E/x0490e00.htm
* http://www.fao.org/nr/water/eto.html

About
-----
I wrote this several years ago while hacking together an [evapotranspiration-based irrigation controller](http://blog.allanglen.com/2009/07/building-a-smart-irrigation-controller-part-1).  This part of the code ran in the cloud behind a REST API that my [home automation controller](http://www.universal-devices.com/residential/isy-99i/) would communicate with to determine irrigation requirements.

[Univeral Devices](http://www.universal-devices.com/), the manufacturer of the controller, ultimately [integrated my design](http://forum.universal-devices.com/viewtopic.php?t=2682) (with my permission) into their firmware so this functionality is now available as an optional feature in all of their devices.

When I was working on this at the time, I couldn't find a software implementation of the Penman-Monteith model (in any language) so hopefully this is useful to someone out there that is looking for a reference implementation.

> Note: While this worked really well for my purposes, no guarantees are made pertaining to the correctness of this implementation.  Use at your own risk.

Description
-----------
Calculates estimated evapotranspiration from a surface using meteorological data.  Requires, elevation, latitude, min and max temperature, min and max humidity and windspeed. A 24 hour calculation period is assumed.

License: 
--------

Copyright 2009 Allan Glen

Licensed under the Apache License, Version 2.0 (the "License");
you may not use this file except in compliance with the License.
You may obtain a copy of the License at

> http://www.apache.org/licenses/LICENSE-2.0

Unless required by applicable law or agreed to in writing, software
distributed under the License is distributed on an "AS IS" BASIS,
WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
See the License for the specific language governing permissions and
limitations under the License.
