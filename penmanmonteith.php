<?php
/*
 * Summary:
 *  A PHP implementation of the United Nations Food and Agriculture Organization (FAO)
 *  Penman-Monteith evapotranspiration equation.
 *
 *  For more information see:
 *   1) http://en.wikipedia.org/wiki/Penman%E2%80%93Monteith_equation
 *   2) http://www.fao.org/docrep/X0490E/x0490e00.htm
 *   3) http://www.fao.org/nr/water/eto.html
 *
 * Description:
 *  Calculates estimated evapotranspiration from a surface using meteorological data.  
 *  Requires, elevation, latitude, min and max temperature, min and max humidity and windspeed.
 *  A 24 hour calculation period is assumed.
 *
 * License: 
 *  Copyright 2009 Allan Glen
 *
 *  Licensed under the Apache License, Version 2.0 (the "License");
 *  you may not use this file except in compliance with the License.
 *  You may obtain a copy of the License at
 *
 *      http://www.apache.org/licenses/LICENSE-2.0
 *
 *  Unless required by applicable law or agreed to in writing, software
 *  distributed under the License is distributed on an "AS IS" BASIS,
 *  WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 *  See the License for the specific language governing permissions and
 *  limitations under the License.
 */

class PenmanMonteith
{
    // The elevation of the weather station
    public $elevationFeet = 0;
    
    // The latitude of the weather station in degrees
    public $latitude = 0;
    
    // The albedo of the surface.  The default albedo (0.23) is based on a reference crop of 0.12 meters
    // and is commonly used for short grass surfaces
    public $albedo = 0.23;
    
    // Solar radiation factor for overcast and cloudy
    private $fractionOfRadiationOvercast = 0.25;
    private $fractionOfRadiationClear = 0.5;

    /*
     * Description:
     *  Calculate the evapotranspiration for a 24 hour period.
     *
     * Parameters:
     *  $temperatureMinF - Minimum temperature in farenheit
     *  $temperatureMaxF - Maximum temperature in farenheit
     *  $relativeHumidityMin - Minimum relative humidity (percent)
     *  $relativeHumidityMax - Maximum relative humidity (percent)
     *  $windspeedMilesPerHour - Wind speed in miles per hour
     *
     * Returns:
     *  Estimated evapotranspiration in inches rounded to 3 decimal places.
     */        
    public function calculateEvapotranspiration($temperatureMinF,$temperatureMaxF,$relativeHumidityMin,
                                                $relativeHumidityMax, $windspeedMilesPerHour) {
        
        // Get the day of the year
        $dayOfYear = $this->dayOfYear();
                
        // Convert input values to metric
        $elevationMeters = $this->feetToMeters($this->elevationFeet);
        $temperatureMinC = $this->farenheitToCelcius($temperatureMinF);
        $temperatureMaxC = $this->farenheitToCelcius($temperatureMaxF);
        $temperatureMeanC = $this->mean($temperatureMinC,$temperatureMaxC);
        $windspeedMetersPerSecond = $this->milesPerHourToMetersPerSecond($windspeedMilesPerHour);
                
        // Calculate meteorological parameters for Penman-Monteith equation
        $atmosphericPressure = $this->atmosphericPressure($elevationMeters);
        
        $psychrometricConstant = $this->psychrometricConstant($atmosphericPressure);
        
        $saturationVapourPressureMin = $this->saturationVapourPressure($temperatureMinC);
        
        $saturationVapourPressureMax = $this->saturationVapourPressure($temperatureMaxC);
    
        $saturationVapourPressureMean = $this->mean($saturationVapourPressureMin,$saturationVapourPressureMax);
        
        $slopeOfSaturationVapourPressureCurve = $this->slopeOfSaturationVapourPressureCurve($saturationVapourPressureMean);
        
        $actualVapourPressure = $this->actualVapourPressure($saturationVapourPressureMin,
                                                            $saturationVapourPressureMax,
                                                            $relativeHumidityMin,
                                                            $relativeHumidityMax);
    
        $vapourPressureDeficit = $this->vapourPressureDeficit($saturationVapourPressureMin,
                                                              $saturationVapourPressureMax,
                                                              $actualVapourPressure);
        
        // Calculate solar radiation paramters for Penman-Monteith equation
        $inverseRelativeDistanceEarthSun = $this->inverseRelativeDistanceEarthSun($dayOfYear);
    
        $solarDeclination = $this->solarDeclination($dayOfYear);
    
        $sunsetHourAngle = $this->sunsetHourAngle($this->latitude,$solarDeclination);
    
        $extraterrestrialRadiationDaily = $this->extraterrestrialRadiationDaily($inverseRelativeDistanceEarthSun,
                                                                                $sunsetHourAngle,
                                                                                $this->latitude,
                                                                                $solarDeclination);
        $daylightHours = $this->daylightHours($sunsetHourAngle);
    
        $clearSkySolarRadiation = $this->solarRadiation($daylightHours,$daylightHours,
                                                        $extraterrestrialRadiationDaily);
        
          // Note: Clear sky is assumed for the purposes of this estimate as actual sunshine hours are not typically available
          // The difference is not significant as changes in direct sunlight typically result in lower temperatures and
          // higher humidity.
        $actualSunshineHours = $daylightHours;
    
        $incomingSolarRadiation = $this->solarRadiation($actualSunshineHours,$daylightHours,
                                                        $extraterrestrialRadiationDaily);
        
        $netSolarRadiation = $this->netSolarRadiation($this->albedo,$incomingSolarRadiation);
        
        $netLongwaveRadiation = $this->netLongwaveRadiation($temperatureMinC,
                                                            $temperatureMaxC,
                                                            $actualVapourPressure,
                                                            $incomingSolarRadiation,
                                                            $clearSkySolarRadiation);
        
        $netRadiation = $this->netRadiation($netSolarRadiation,$netLongwaveRadiation);
        
        // Calculation the evapotranspiration (metric)
        $evapotranspirationMm = $this->penmanMoneteithEvapotranspiration($slopeOfSaturationVapourPressureCurve,
                                                                         $netRadiation,
                                                                         $psychrometricConstant,
                                                                         $temperatureMeanC,
                                                                         $windspeedMetersPerSecond,
                                                                         $vapourPressureDeficit);
        
        // Convert evapotranpiration to inches
        $evapotranspirationIn = $this->mmToIn($evapotranspirationMm);

        return round($evapotranspirationIn,3);
    }

    /*
     * Description:
     *  Calculate the estimated evapotranspiration in millimeters for a 24 hour period.
     *
     * Parameters:
     *  $slopeOfSaturationVapourPressureCurve - Slope of the saturation vapour pressure curve
     *  $netRadiation - Net radiation for the 24 hour period
     *  $psychrometricConstant - The psychrometric constant
     *  $temperatureMeanC - Mean daily temperature in celcius
     *  $windspeedMetersPerSecond - Wind speed in meters per second
     *  $vapourPressureDeficit - Vapour pressure defecit
     *
     * Returns:
     *  Estimated evapotranspiration in millimeters.
     */    
    private function penmanMoneteithEvapotranspiration($slopeOfSaturationVapourPressureCurve,
                                                       $netRadiation,
                                                       $psychrometricConstant,
                                                       $temperatureMeanC,
                                                       $windspeedMetersPerSecond,
                                                       $vapourPressureDeficit) {
        
      $numerator = (0.408 * $slopeOfSaturationVapourPressureCurve * $netRadiation) +
                    ($psychrometricConstant * (900 / ($temperatureMeanC + 273)) * $windspeedMetersPerSecond * $vapourPressureDeficit);
                    
      $denominator = $slopeOfSaturationVapourPressureCurve + ($psychrometricConstant * (1 + (0.34 * $windspeedMetersPerSecond)));
      
     return $numerator / $denominator;
    }
    
    /*
     * Description:
     *  Convert farenheit to celcius.
     *
     * Parameters:
     *  $farenheit - Temperature in farenheit
     *
     * Returns:
     *  Temperature in celcius.
     */    
    private function farenheitToCelcius($farenheit) {
      return ($farenheit - 32) * (5/9);
    }

    /*
     * Description:
     *  Convert celcius to kelvin.
     *
     * Parameters:
     *  $celcius - Temperature in celcius
     *
     * Returns:
     *  Temperature in kelvin.
     */    
    private function celciusToKelvin($celcius) {
     return $celcius + 273.16;
    }

    /*
     * Description:
     *  Convert feet to meters.
     *
     * Parameters:
     *  $feet - Distance in feet
     *
     * Returns:
     *  Distance in meters.
     */     
    private function feetToMeters($feet) {
      return $feet * 0.3048;
    }

    /*
     * Description:
     *  Convert miles per hour to meters per second.
     *
     * Parameters:
     *  $milesPerHour - Miles per hours
     *
     * Returns:
     *  Speed in meters per second.
     */    
    private function milesPerHourToMetersPerSecond($milesPerHour) {
     return $milesPerHour * 0.44704;
    }

    /*
     * Description:
     *  Convert millimeters to inches.
     *
     * Parameters:
     *  $mm - Millimeters
     *
     * Returns:
     *  Distance in inches.
     */             
    private function mmToIn($mm) {
     return $mm * 0.0393700787;
    }
    
    
    /*
     * Description:
     *  Returns the current day of the year based on the system clock.  
     *
     * Returns:
     *  Day of the year where 1 is the first day of the year.
     */             
    private function dayOfYear() {
     return date("z") + 1;
    }

    /*
     * Description:
     *  Calculates the estimated atmospheric pressure at a specific elevation based on the ideal gas law at 20 degrees C. 
     *  See http://www.fao.org/docrep/X0490E/x0490e07.htm#atmospheric parameters
     *
     * Parameters:
     *  $elevationMeters - Elevation in meters
     *
     * Returns:
     *  Atmospheric pressure (kPa)
     */                 
    // Atmospheric pressure (P)
    private function atmosphericPressure($elevationMeters) {
      return 101.3 * pow(((293 - (0.0065 * $elevationMeters))/293),5.26);
    }

    /*
     * Description:
     *  Calculates the psychrometric constant atmospheric pressure at a specific elevation based on the ideal gas law at 20 degrees C. 
     *  See http://www.fao.org/docrep/X0490E/x0490e07.htm#psychrometric constant (g)
     *
     * Parameters:
     *  $atmosphericPressure - Atmospheric pressure in kPa
     *
     * Returns:
     *  Psychrometric constant (kPa °C-1)
     */    
    private function psychrometricConstant($atmosphericPressure)
    {
     return 0.000665 * $atmosphericPressure;
    }

    /*
     * Description:
     *  Calculates the saturation vapour pressure at a specified temperature
     *  See http://www.fao.org/docrep/X0490E/x0490e07.htm#air humidity
     *
     * Parameters:
     *  $temperatureC - Temperature in celcius
     *
     * Returns:
     *  Saturation vapour pressure (kPa)
     */    
    private function saturationVapourPressure($temperatureC) {
      return 0.6108 * exp((17.27 * $temperatureC) / ($temperatureC + 237.3));   
    }
    
    /*
     * Description:
     *  Calculate the mean (average) of two values)
     *
     * Parameters:
     *  $value1 - The first value
     *  $value2 - The second value
     *
     * Returns:
     *  The mean of the two parameters
     */
    private function mean($value1, $value2) {
     return ($value1 + $value2) / 2;
    }
    
    /*
     * Description:
     *  Calculates the slope of the saturation vapour pressure curve at a specified temperature
     *  See http://www.fao.org/docrep/X0490E/x0490e07.htm#air humidity
     *
     * Parameters:
     *  $temperatureC - Temperature in celcius
     *
     * Returns:
     *  Slope of the saturation vapour pressure curve (kPa °C-1)
     */ 
    private function slopeOfSaturationVapourPressureCurve($temperatureC) {
      return (4098 * $this->saturationVapourPressure($temperatureC)) / pow(($temperatureC + 237.3),2);
    }
 
    /*
     * Description:
     *  Calculates the actual vapour pressure
     *  See http://www.fao.org/docrep/X0490E/x0490e07.htm#air humidity
     *
     * Parameters:
     *  $saturationVapourPressureMin - Minimum saturation vapour pressure (kPa)
     *  $saturationVapourPressureMax - Maximum saturation vapour pressure (kPa)
     *  $relativeHumidityMin - Minimum relative humidity (%)
     *  $relativeHumidityMax - Masimum relative humidity (%)
     *
     * Returns:
     *  Actual vapour pressure (kPa)
     */    
    private function actualVapourPressure($saturationVapourPressureMin,$saturationVapourPressureMax,
                                          $relativeHumidityMin,$relativeHumidityMax) {
     return $this->mean($saturationVapourPressureMin * ($relativeHumidityMax / 100),
                        $saturationVapourPressureMax * ($relativeHumidityMin / 100));
    }
    
    /*
     * Description:
     *  Calculates the vapour pressure deficit
     *  See http://www.fao.org/docrep/X0490E/x0490e07.htm#air humidity
     *
     * Parameters:
     *  $saturationVapourPressureMin - Minimum saturation vapour pressure (kPa)
     *  $saturationVapourPressureMax - Maximum saturation vapour pressure (kPa)
     *  $actualVapourPressure - Actual vapour pressure (kPa)
     *
     * Returns:
     *  Vapour pressure deficit (kPa)
     */ 
    private function vapourPressureDeficit($saturationVapourPressureMin,$saturationVapourPressureMax,
                                           $actualVapourPressure) {
     return $this->mean($saturationVapourPressureMin,$saturationVapourPressureMax) - $actualVapourPressure;
    }

    /*
     * Description:
     *  Calculates the daily extraterrestrial radiation at a specific latitude at a specific time of year
     *  See http://www.fao.org/docrep/X0490E/x0490e07.htm#radiation
     *
     * Parameters:
     *  $inverseRelativeDistanceEarthSun - Inverse relative distance Earth-Sun
     *  $sunsetHourAngle - Sunset hour angle (radians)
     *  $latitude - Latitude (degrees)
     *  $solarDeclination - Solar declination (radians)
     *
     * Returns:
     *  Extraterrestrial radiation (MJ m-2 day-1)
     */    
    private function extraterrestrialRadiationDaily($inverseRelativeDistanceEarthSun, $sunsetHourAngle, $latitude, $solarDeclination) {
      $solarConstant = 0.0820;
      
      $angleCalculation = ($sunsetHourAngle * sin(deg2rad($latitude)) * sin($solarDeclination)) +
                            (cos(deg2rad($latitude)) * cos($solarDeclination) * sin($sunsetHourAngle)); 
      
      return ((24 * 60) / pi()) * $solarConstant * $inverseRelativeDistanceEarthSun * ( $angleCalculation );
    }
    
    /*
     * Description:
     *  Calculates the inverse relative distance earth-sun based on the day of the year.
     *  See http://www.fao.org/docrep/X0490E/x0490e07.htm#radiation
     *
     * Parameters:
     *  $dayOfYear - Day of the year where 1 is the first day of the year
     *
     * Returns:
     *  Inverse relative distance earth-sun (radians)
     */
    private function inverseRelativeDistanceEarthSun($dayOfYear) {
     return 1 + (0.033 * cos(((2 * pi()) / 365) * $dayOfYear));
    }

    /*
     * Description:
     *  Calculates the solar declination based on the day of the year.
     *  See http://www.fao.org/docrep/X0490E/x0490e07.htm#radiation
     *
     * Parameters:
     *  $dayOfYear - Day of the year where 1 is the first day of the year
     *
     * Returns:
     *  Solar declination (radians)
     */    
    private function solarDeclination($dayOfYear) {
     return 0.409 * sin((((2 * pi())/365)*$dayOfYear)-1.39);
    }

    /*
     * Description:
     *  Calculates the sunset hour angle based on the latitude and the solar declination
     *  See http://www.fao.org/docrep/X0490E/x0490e07.htm#radiation
     *
     * Parameters:
     *  $latitude - Latitude (degrees)
     *  $solarDeclination - Solar declination (radians)
     *
     * Returns:
     *  Sunset hour angle (radians)
     */    
    private function sunsetHourAngle($latitude,$solarDeclination) {
     return acos(-tan(deg2rad($latitude)) * tan($solarDeclination));
    }
    
    /*
     * Description:
     *  Calculates the daylight hours given the sunset hour angle
     *  See http://www.fao.org/docrep/X0490E/x0490e07.htm#radiation
     *
     * Parameters:
     *  $sunsetHourAngle - Sunset hour angle (radians)
     *
     * Returns:
     *  Daylight hours
     */  
    private function daylightHours($sunsetHourAngle) {
      return (24 / pi()) * $sunsetHourAngle;
    }

    /*
     * Description:
     *  Calculates the solar radiation
     *  See http://www.fao.org/docrep/X0490E/x0490e07.htm#radiation
     *
     * Parameters:
     *  $sunshineHoursActual - Actual sunshine hours
     *  $sunshineHoursMax - Maximum sunshine hours
     *  $extraterrestrialRadiationDaily - Daily extraterrestrial radiation (MJ m-2 day-1)
     *
     * Returns:
     *  Daylight hours
     */    
    private function solarRadiation($sunshineHoursActual,$sunshineHoursMax,$extraterrestrialRadiationDaily) {
      
      $fractionOfRadiationOvercast = $this->fractionOfRadiationOvercast;
      $fractionOfRadiationClear = $this->fractionOfRadiationClear;
      
      return ($fractionOfRadiationOvercast + ($fractionOfRadiationClear*($sunshineHoursActual / $sunshineHoursMax))) *
                $extraterrestrialRadiationDaily;
    }

    /*
     * Description:
     *  Calculates the net solar radiation
     *  See http://www.fao.org/docrep/X0490E/x0490e07.htm#radiation
     *
     * Parameters:
     *  $albedo - Albedo of the surface
     *  $incomingSolarRadiation - Incoming solar radiation (MJ m-2 day-1)
     *
     * Returns:
     *  Net solar radiation (MJ m-2 day-1)
     */    
    private function netSolarRadiation($albedo,$incomingSolarRadiation) {
      return (1 - $albedo) * $incomingSolarRadiation;
    }

    /*
     * Description:
     *  Calculates the net longwave radiation
     *  See http://www.fao.org/docrep/X0490E/x0490e07.htm#radiation
     *
     * Parameters:
     *  $temperatureMinC - Minimum temperature (C)
     *  $temperatureMaxC - Maximum temperature (C)
     *  $actualVapourPressure - Actual vapour pressure (kPa)
     *  $incomingSolarRadiation - Incoming solar radiation (MJ m-2 day-1)
     *  $clearSkySolarRadiation - Clear sky solar radiation (MJ m-2 day-1)
     *
     * Returns:
     *  Net longwave radiation (MJ m-2 day-1)
     */       
    private function netLongwaveRadiation($temperatureMinC,$temperatureMaxC,$actualVapourPressure,$incomingSolarRadiation,$clearSkySolarRadiation) {
      
      // Convert temperature to kelvin
      $temperatureMinK = $this->celciusToKelvin($temperatureMinC);
      $temperatureMaxK = $this->celciusToKelvin($temperatureMaxC);
      
      // Calculate the average temperature to the 4th power
      $temperatureAvgFourthPower = (pow($temperatureMinK,4) + pow($temperatureMaxK,4)) / 2;
      
      // Calculate the relative shortwave radiation
      $relativeShortwaveRadiation = $incomingSolarRadiation / $clearSkySolarRadiation;
      
      // Calculate the net longwave radiation
      return 0.000000004903 * $temperatureAvgFourthPower *
            (0.34 - (0.14 * sqrt($actualVapourPressure))) * ((1.35 * $relativeShortwaveRadiation) - 0.35);
    }
    
    /*
     * Description:
     *  Calculates the net radiation
     *  See http://www.fao.org/docrep/X0490E/x0490e07.htm#radiation
     *
     * Parameters:
     *  $netSolarRadiation - Net solar radiation (MJ m-2 day-1)
     *  $netLongwaveRadiation - Net longwave radiation (MJ m-2 day-1)
     *
     * Returns:
     *  Net radiation (MJ m-2 day-1)
     */
    private function netRadiation($netSolarRadiation,$netLongwaveRadiation) {
      return $netSolarRadiation - $netLongwaveRadiation;
    }
        
}
?>