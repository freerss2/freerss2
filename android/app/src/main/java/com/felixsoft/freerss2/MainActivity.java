package com.felixsoft.freerss2;

import android.annotation.SuppressLint;

import android.annotation.TargetApi;
import android.app.Activity;
import android.content.Context;
import android.content.Intent;
import android.content.pm.PackageManager;
import android.net.Uri;
import android.os.Build;
import android.support.v4.widget.SwipeRefreshLayout;
import android.support.v7.app.AppCompatActivity;
import android.os.Bundle;
import android.view.KeyEvent;
import android.webkit.PermissionRequest;
import android.webkit.URLUtil;
import android.webkit.WebChromeClient;
import android.webkit.WebResourceRequest;
import android.webkit.WebSettings;
import android.webkit.WebView;
import android.webkit.WebViewClient;
import android.widget.Toast;

public class MainActivity extends AppCompatActivity {

    private WebView webView;
    SwipeRefreshLayout swipeRefreshLayout;

    String appBaseUrl = "https://freerss2.freecluster.eu/";

    @SuppressLint("SetJavaScriptEnabled")
    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        setContentView(R.layout.activity_main);

        CustomWebViewClient client = new CustomWebViewClient( this );
        webView = findViewById(R.id.webview);
        swipeRefreshLayout  = findViewById(R.id.reload);
        webView.setWebViewClient(client);

        WebSettings webSettings = webView.getSettings();
        webSettings.setJavaScriptEnabled(true);
        webSettings.setBuiltInZoomControls(true);
        webSettings.setDomStorageEnabled(true);
        webSettings.setSaveFormData(true);
        // webSettings.setSavePassword(true);
        webView.loadUrl(String.join("/", appBaseUrl, "mobile_client.php"));


        webView.setWebChromeClient(new WebChromeClient() {
                                       @Override
                                       public void onPermissionRequest(final PermissionRequest request) {
                                           runOnUiThread(new Runnable() {
                                               @TargetApi(Build.VERSION_CODES.LOLLIPOP)
                                               @Override
                                               public void run() {
                                                   // request permission for notifications
                                                   if (request.getOrigin().toString().equals(appBaseUrl)) {
                                                       request.grant(request.getResources());
                                                   } else {
                                                       request.deny();
                                                   }
                                               }
                                           });
                                       }
                                   });

        swipeRefreshLayout.setOnRefreshListener(new SwipeRefreshLayout.OnRefreshListener() {
            @Override
            public void onRefresh() {
                webView.reload();
            }
        });

        webView.setWebViewClient(new WebViewClient(){
            @Override
            public void onPageFinished(WebView view, String url){
                super.onPageFinished(view, url);
                swipeRefreshLayout.setRefreshing(false);
            }

            // for API Level less than 24
            @Override
            public boolean shouldOverrideUrlLoading(WebView webView, String url) {

                Context context = webView.getContext();
                Bundle bundle = new Bundle();
                if (url.startsWith ("whatsapp://")) {
                    webView.stopLoading();
                    try {
                        Intent whatsappIntent = new Intent (Intent.ACTION_SEND);
                        whatsappIntent.setType ("text/plain");
                        whatsappIntent.setPackage ("com.whatsapp");
                        whatsappIntent.putExtra (Intent.EXTRA_TEXT, webView.getUrl());
                        startActivity (whatsappIntent);
                    } catch (android.content.ActivityNotFoundException ex) {
                        String MakeShortText = "Whatsapp failed: " + ex.toString();
                        Toast.makeText (context, MakeShortText, Toast.LENGTH_SHORT).show();
                    }
                }
                if (url.startsWith ("market://") || url.startsWith ("mailto:") || url.startsWith ("fb://") || url.startsWith ("fb-service://")) {
                    Intent intent = new Intent(Intent.ACTION_VIEW);
                    intent.setData(Uri.parse(url));
                    startActivity(intent);
                    return true;
                }
                if( URLUtil.isNetworkUrl(url) ) {
                    // customize according to URL - return true for open in browser app
                    return false;
                } else {
                    if (appInstalledOrNot(context, url)) {
                        Intent intent = new Intent(Intent.ACTION_VIEW, Uri.parse(url));
                        startActivity(intent);
                    } else {
                        return false;
                        // indicate if app is not installed:
                        /*
                        String MakeShortText = "app is not installed";
                        Toast.makeText (context, MakeShortText, Toast.LENGTH_SHORT).show();
                         */
                    }
                }

                return true;
            }

            // for API Level >= 24
            @Override
            public boolean shouldOverrideUrlLoading(WebView webView, WebResourceRequest request) {
                return shouldOverrideUrlLoading(webView, request.getUrl().toString());
                // return false;
            }

            private boolean appInstalledOrNot(Context context, String uri) {
                PackageManager pm = context.getPackageManager();
                try {
                    pm.getPackageInfo(uri, PackageManager.GET_ACTIVITIES);
                    return true;
                } catch (PackageManager.NameNotFoundException e) {
                }

                return false;
            }
        });

    }

    @Override
    public boolean onKeyDown(int keyCode, KeyEvent event) {
        if(keyCode == KeyEvent.KEYCODE_BACK && this.webView.canGoBack()) {
            this.webView.goBack();
            return true;
        }
        return super.onKeyDown(keyCode, event);
    }

    @Override
    public void onBackPressed() {
        if (webView.isFocused() && webView.canGoBack()) {
            webView.goBack();
        } else {
            super.onBackPressed();
        }
    }
}

class CustomWebViewClient extends WebViewClient {
    private Activity activity;

    public CustomWebViewClient(Activity activity){
        this.activity = activity;
    }

    // for API Level less than 24
    @Override
    public boolean shouldOverrideUrlLoading(WebView webView, String url){
        // TODO: customize according to URL - return true for open in browser app
        return false;
    }

    // for API Level >= 24
    @Override
    public boolean shouldOverrideUrlLoading(WebView webView, WebResourceRequest request) {
        return false;
    }
}
