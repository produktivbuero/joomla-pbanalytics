var cookieName = window.pb.analytics.cookie.name;

if (document.cookie.indexOf(cookieName + '=true') > -1) {
  /* global Gooogle Opt Out-Cookie */
  document.cookie = 'ga-disable-'+window.pb.analytics.ga.property + '=true; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/';
  window['ga-disable-'+window.pb.analytics.ga.property] = true;

  document.getElementById('analyticsOptOut').innerHTML = window.pb.analytics.link.enable;
  document.getElementById('analyticsStatus').innerHTML = window.pb.analytics.status.disabled;
}


function pbAnalyticsOptOut() {
  if (document.cookie.indexOf(cookieName + '=true') > -1) {
    document.cookie = cookieName + '=; ;expires=Thu, 01 Jan 1970 00:00:01 UTC; path=/';
    document.getElementById('analyticsOptOut').innerHTML = window.pb.analytics.link.disable;
    document.getElementById('analyticsStatus').innerHTML = window.pb.analytics.status.enabled;
  } else {
    document.cookie = cookieName + '=true; expires=Thu, 31 Dec 2099 23:59:59 UTC; path=/';
    delete window['ga-disable-'+window.pb.analytics.ga.property];
    document.getElementById('analyticsOptOut').innerHTML = window.pb.analytics.link.enable;
    document.getElementById('analyticsStatus').innerHTML = window.pb.analytics.status.disabled;
  }
}
