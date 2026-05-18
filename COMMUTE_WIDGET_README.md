# Google Maps Commute Widget Integration

## Overview
The Google Maps Commute Widget has been successfully integrated into the Walbrand Movers booking system. This widget provides users with an interactive tool to estimate commute times and plan their moves more effectively.

## Features
- **Interactive Map**: Full-screen map interface for location exploration
- **Destination Management**: Add, edit, and delete multiple destinations
- **Travel Mode Options**: Driving, transit, bicycling, and walking modes
- **Real-time Directions**: Get accurate travel times and routes
- **Google Maps Integration**: Powered by Google Maps JavaScript API

## Files Created/Modified

### New Files
- `commutes_widget.php` - Standalone commute widget page

### Modified Files
- `book_moving_service.php` - Added commute widget integration

## Integration Details

### Booking Page Integration
The commute widget is embedded in the booking form as an iframe within the "Location & Distance" section. It appears below the route preview map and provides additional functionality for users to:

1. **Explore Locations**: Use the interactive map to explore potential moving destinations
2. **Estimate Commute Times**: Add destinations to see travel times from their current location
3. **Plan Routes**: Get detailed directions and route information
4. **Compare Options**: Switch between different travel modes (driving, transit, etc.)

### Technical Implementation
- **API Key**: Uses the existing Google Maps API key (`AIzaSyChidKg7Jxuzfx87haL5THWmdKfzwp11PI`)
- **Libraries**: Includes Places, Geometry, and Directions services
- **Responsive Design**: Adapts to different screen sizes
- **Error Handling**: Graceful fallback if Google Maps fails to load

## Usage Instructions

### For Users
1. Navigate to the moving service booking page
2. Scroll to the "Moving Locations" section
3. Use the "Commute Time Estimator" widget to:
   - Click "Add destination" to add locations
   - Select travel modes (driving, transit, etc.)
   - View estimated travel times
   - Get detailed directions

### For Developers
The widget can be used independently by accessing `commutes_widget.php` directly, or embedded in other pages using an iframe:

```html
<iframe  width="100%" height="500" frameborder="0"></iframe>
```

## Configuration
The widget is configured with:
- **Center Location**: Nairobi, Kenya (-0.023559, 37.906193)
- **Default Travel Mode**: Driving
- **Distance Units**: Metric (kilometers)
- **Max Destinations**: 10

## API Requirements
Ensure the following Google Maps APIs are enabled in Google Cloud Console:
- Maps JavaScript API
- Places API
- Directions API
- Distance Matrix API (optional)

## Troubleshooting

### Widget Not Loading
1. Check that Google Maps JavaScript API is enabled
2. Verify API key is valid and has correct restrictions
3. Ensure billing is enabled on Google Cloud Project

### Map Errors
- Check browser console for JavaScript errors
- Verify network connectivity
- Confirm API key permissions

## Future Enhancements
- Integration with booking form data
- Save user destinations
- Route optimization features
- Mobile app integration