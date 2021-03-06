$(function(){

  // 定数定義
  var csvFile = '';                     // CSVファイル名

  var map = L.map('mapdiv', {
    minZoom: 6,
    maxZoom: 19
  });

  // 地理院地図
  var newLayer = L.tileLayer(
    'https://cyberjapandata.gsi.go.jp/xyz/pale/{z}/{x}/{y}.png', {
       opacity: 0.6,
       attribution: '<a href="http://www.gsi.go.jp/kikakuchousei/kikakuchousei40182.html" target="_blank">国土地理院</a>'
  });

  newLayer.addTo(map);

  L.control.locate({
    position: 'topright',
    drawCircle: false, 
    follow: false,
    setView: true,
    keepCurrentZoomLevel: true,
    stopFollowingOnDrag: true,
    remainActive: false,
    markerClass: L.circleMarker, // L.circleMarker or L.marker
    circleStyle: {},
    markerStyle: {},
    followCircleStyle: {},
    followMarkerStyle: {},
    icon: 'fa fa-location-arrow',
    iconLoading: 'fa fa-spinner fa-spin',
    showPopup: false,
    locateOptions: {enableHighAccuracy: true}
  }).addTo(map);

  L.control.scale({imperial: false}).addTo(map);

  L.easyButton('fa fa-info fa-lg',
    function() {
      $('#about').modal('show');
    },
    'このサイトについて',
    null, {
      position:  'bottomright'
    }).addTo(map);

  var mCluster = new L.markerClusterGroup({
    showCoverageOnHover: false,
    maxClusterRadius: 40,
    spiderfyDistanceMultiplier: 2
  });
  map.addLayer(mCluster);

  var csvLayer = omnivore.csv(csvFile,
    {latfield: 'lat', lonfield: 'lon', delimiter: ','});

  csvLayer.on('ready', function(layer) {
    this.eachLayer(function(marker) {
      mCluster.addLayer(marker);
      marker.setIcon(L.ExtraMarkers.icon({
        markerColor: 'blue',
      }));
      marker.bindPopup(
        decodeURIComponent(marker.toGeoJSON().properties.embed_html),
        {closeButton: false}
      );

      marker.on('popupopen', function(layer) { 
        twttr.widgets.load(document.getElementsByClassName('leaflet-popup-pane'));
        twttr.events.bind(
          'rendered',
          function (event) {
            // ポップアップの位置調整
            $('.leaflet-popup-pane').css('left', '-124px'); 
            $('.leaflet-popup-pane').show();
          }
        );
      });
      marker.on('popupclose', function(layer) { 
        $('.leaflet-popup-pane').hide();
      });
    });

    var lat=0, lng=0, zoom=0;
    var pstr = location.search.substring(1);
    if(pstr) {
      var parr = pstr.split('&');
      var ppair;
      for (i = 0; i < parr.length; i++) {
        ppair = parr[i].split('=');
        switch (ppair[0]){
          case 'x':
            lng = ppair[1];
            break;
          case 'y':
            lat = ppair[1];
            break;
          case 'z':
            zoom = ppair[1];
            break;
        }
      }
      if(lat !=0 && lng != 0 && zoom != 0) {
        map.setView([lat, lng], zoom);
      } else {
        map.fitBounds(csvLayer.getBounds());
      }
    } else {
      map.fitBounds(csvLayer.getBounds());
    }

    map.on('moveend', function() {
      history.replaceState(null, null, "?x=" + map.getCenter().lng +
                                       "&y=" + map.getCenter().lat + 
                                       "&z=" + map.getZoom());
    });

    $('.leaflet-popup-pane').hide();
  });
});
