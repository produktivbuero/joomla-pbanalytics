const cookieName = window.pb.analytics.cookie.name;
const $analyticsOptOut = document.getElementById('analyticsOptOut');
const $analyticsStatus = document.getElementById('analyticsStatus');

if (document.cookie.indexOf(cookieName + '=true') > -1) {
  /* global Gooogle Opt Out-Cookie */
  document.cookie = 'ga-disable-'+window.pb.analytics.ga.property + '=true; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/';
  window['ga-disable-'+window.pb.analytics.ga.property] = true;

  if ( $analyticsOptOut !== null ) $analyticsOptOut.innerHTML = window.pb.analytics.link.enable;
  if ( $analyticsStatus !== null ) $analyticsStatus.innerHTML = window.pb.analytics.status.disabled;
}


function pbAnalyticsOptOut() {
  if (document.cookie.indexOf(cookieName + '=true') > -1) {
    document.cookie = cookieName + '=; ;expires=Thu, 01 Jan 1970 00:00:01 UTC; path=/';
    $analyticsOptOut.innerHTML = window.pb.analytics.link.disable;
    $analyticsStatus.innerHTML = window.pb.analytics.status.enabled;
  } else {
    document.cookie = cookieName + '=true; expires=Thu, 31 Dec 2099 23:59:59 UTC; path=/';
    delete window['ga-disable-'+window.pb.analytics.ga.property];
    $analyticsOptOut.innerHTML = window.pb.analytics.link.enable;
    $analyticsStatus.innerHTML = window.pb.analytics.status.disabled;
  }
}
