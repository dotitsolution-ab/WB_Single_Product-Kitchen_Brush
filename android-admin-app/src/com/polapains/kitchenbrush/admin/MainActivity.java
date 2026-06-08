package com.polapains.kitchenbrush.admin;

import android.app.Activity;
import android.app.AlertDialog;
import android.app.Notification;
import android.app.NotificationChannel;
import android.app.NotificationManager;
import android.app.PendingIntent;
import android.Manifest;
import android.content.Context;
import android.content.ActivityNotFoundException;
import android.content.DialogInterface;
import android.content.Intent;
import android.content.pm.PackageManager;
import android.graphics.Color;
import android.net.ConnectivityManager;
import android.net.NetworkInfo;
import android.net.Uri;
import android.os.Build;
import android.os.Bundle;
import android.view.Gravity;
import android.view.View;
import android.view.ViewGroup;
import android.webkit.CookieManager;
import android.webkit.JavascriptInterface;
import android.webkit.JsResult;
import android.webkit.WebChromeClient;
import android.webkit.WebResourceResponse;
import android.webkit.WebResourceRequest;
import android.webkit.WebSettings;
import android.webkit.WebView;
import android.webkit.WebViewClient;
import android.widget.FrameLayout;
import android.widget.ProgressBar;
import android.widget.TextView;
import android.widget.Toast;

public class MainActivity extends Activity {
    private static final String ADMIN_HOST = "kitchenbrush.hellowb.com";
    private static final String CHANNEL_ID = "orders";
    private static final int NOTIFICATION_PERMISSION_REQUEST = 42;
    private WebView webView;
    private ProgressBar progressBar;
    private TextView offlineView;
    private boolean fallbackLoaded;

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        buildLayout();
        configureWebView();
        createNotificationChannel();
        requestNotificationPermissionIfNeeded();

        if (savedInstanceState == null) {
            loadInitialUrl();
        } else {
            webView.restoreState(savedInstanceState);
        }
    }

    @Override
    protected void onNewIntent(Intent intent) {
        super.onNewIntent(intent);
        setIntent(intent);
        loadInitialUrl();
    }

    @Override
    protected void onSaveInstanceState(Bundle outState) {
        super.onSaveInstanceState(outState);
        webView.saveState(outState);
    }

    private void buildLayout() {
        FrameLayout root = new FrameLayout(this);
        root.setBackgroundColor(Color.rgb(238, 243, 239));

        webView = new WebView(this);
        webView.setLayoutParams(new FrameLayout.LayoutParams(
            ViewGroup.LayoutParams.MATCH_PARENT,
            ViewGroup.LayoutParams.MATCH_PARENT
        ));
        root.addView(webView);

        progressBar = new ProgressBar(this, null, android.R.attr.progressBarStyleHorizontal);
        FrameLayout.LayoutParams progressParams = new FrameLayout.LayoutParams(
            ViewGroup.LayoutParams.MATCH_PARENT,
            8
        );
        progressParams.gravity = Gravity.TOP;
        progressBar.setLayoutParams(progressParams);
        progressBar.setMax(100);
        root.addView(progressBar);

        offlineView = new TextView(this);
        offlineView.setText("No internet connection. Tap to retry.");
        offlineView.setTextColor(Color.rgb(15, 23, 42));
        offlineView.setTextSize(17);
        offlineView.setGravity(Gravity.CENTER);
        offlineView.setVisibility(View.GONE);
        offlineView.setBackgroundColor(Color.rgb(238, 243, 239));
        offlineView.setOnClickListener(new View.OnClickListener() {
            @Override
            public void onClick(View view) {
                loadAdminApp();
            }
        });
        root.addView(offlineView, new FrameLayout.LayoutParams(
            ViewGroup.LayoutParams.MATCH_PARENT,
            ViewGroup.LayoutParams.MATCH_PARENT
        ));

        setContentView(root);
    }

    private void configureWebView() {
        CookieManager cookieManager = CookieManager.getInstance();
        cookieManager.setAcceptCookie(true);
        cookieManager.setAcceptThirdPartyCookies(webView, true);

        WebSettings settings = webView.getSettings();
        settings.setJavaScriptEnabled(true);
        settings.setDomStorageEnabled(true);
        settings.setDatabaseEnabled(true);
        settings.setLoadWithOverviewMode(true);
        settings.setUseWideViewPort(true);
        settings.setSupportZoom(false);
        settings.setBuiltInZoomControls(false);
        settings.setCacheMode(WebSettings.LOAD_DEFAULT);

        webView.setWebViewClient(new AdminWebViewClient());
        webView.setWebChromeClient(new AdminChromeClient());
        webView.addJavascriptInterface(new AdminBridge(), "AndroidAdmin");
    }

    private void loadInitialUrl() {
        String targetUrl = getIntent().getStringExtra("target_url");
        if (targetUrl == null || targetUrl.length() == 0) {
            loadAdminApp();
            return;
        }

        if (!isOnline()) {
            offlineView.setVisibility(View.VISIBLE);
            return;
        }

        offlineView.setVisibility(View.GONE);
        fallbackLoaded = false;
        webView.loadUrl(targetUrl);
    }

    private void loadAdminApp() {
        if (!isOnline()) {
            offlineView.setVisibility(View.VISIBLE);
            return;
        }

        offlineView.setVisibility(View.GONE);
        fallbackLoaded = false;
        webView.loadUrl(getString(R.string.admin_url));
    }

    private boolean isOnline() {
        ConnectivityManager manager = (ConnectivityManager) getSystemService(CONNECTIVITY_SERVICE);
        if (manager == null) {
            return true;
        }

        NetworkInfo info = manager.getActiveNetworkInfo();
        return info != null && info.isConnected();
    }

    private void createNotificationChannel() {
        if (Build.VERSION.SDK_INT < 26) {
            return;
        }

        NotificationChannel channel = new NotificationChannel(
            CHANNEL_ID,
            "Order notifications",
            NotificationManager.IMPORTANCE_HIGH
        );
        channel.setDescription("New order alerts from the admin app.");

        NotificationManager manager = (NotificationManager) getSystemService(Context.NOTIFICATION_SERVICE);
        if (manager != null) {
            manager.createNotificationChannel(channel);
        }
    }

    private void requestNotificationPermissionIfNeeded() {
        if (Build.VERSION.SDK_INT < 33) {
            return;
        }

        if (checkSelfPermission(Manifest.permission.POST_NOTIFICATIONS) != PackageManager.PERMISSION_GRANTED) {
            requestPermissions(new String[]{Manifest.permission.POST_NOTIFICATIONS}, NOTIFICATION_PERMISSION_REQUEST);
        }
    }

    private boolean canShowNotifications() {
        return Build.VERSION.SDK_INT < 33
            || checkSelfPermission(Manifest.permission.POST_NOTIFICATIONS) == PackageManager.PERMISSION_GRANTED;
    }

    private void showOrderNotification(String title, String body, String targetUrl) {
        if (!canShowNotifications()) {
            return;
        }

        Intent intent = new Intent(this, MainActivity.class);
        intent.setFlags(Intent.FLAG_ACTIVITY_SINGLE_TOP | Intent.FLAG_ACTIVITY_CLEAR_TOP);
        intent.putExtra("target_url", targetUrl);

        int flags = PendingIntent.FLAG_UPDATE_CURRENT;
        if (Build.VERSION.SDK_INT >= 23) {
            flags |= PendingIntent.FLAG_IMMUTABLE;
        }

        PendingIntent pendingIntent = PendingIntent.getActivity(
            this,
            Math.abs(targetUrl.hashCode()),
            intent,
            flags
        );

        Notification.Builder builder = Build.VERSION.SDK_INT >= 26
            ? new Notification.Builder(this, CHANNEL_ID)
            : new Notification.Builder(this);

        builder
            .setSmallIcon(R.drawable.ic_launcher)
            .setContentTitle(title)
            .setContentText(body)
            .setContentIntent(pendingIntent)
            .setAutoCancel(true)
            .setShowWhen(true);

        if (Build.VERSION.SDK_INT >= 21) {
            builder.setColor(Color.rgb(15, 118, 110));
        }

        NotificationManager manager = (NotificationManager) getSystemService(Context.NOTIFICATION_SERVICE);
        if (manager != null) {
            manager.notify(Math.abs(targetUrl.hashCode()), builder.build());
        }
    }

    private boolean shouldOpenExternally(Uri uri) {
        String scheme = uri.getScheme();
        if (scheme == null) {
            return false;
        }

        if ("tel".equals(scheme) || "mailto".equals(scheme) || "sms".equals(scheme) || "intent".equals(scheme)) {
            return true;
        }

        String host = uri.getHost();
        return host != null && !ADMIN_HOST.equalsIgnoreCase(host);
    }

    private void openExternal(Uri uri) {
        try {
            startActivity(new Intent(Intent.ACTION_VIEW, uri));
        } catch (ActivityNotFoundException exception) {
            Toast.makeText(this, "No app found to open this link.", Toast.LENGTH_SHORT).show();
        }
    }

    private boolean shouldFallbackToLogin(String url, int statusCode) {
        if (fallbackLoaded || statusCode < 400 || url == null) {
            return false;
        }

        Uri uri = Uri.parse(url);
        String host = uri.getHost();
        String path = uri.getPath();

        return ADMIN_HOST.equalsIgnoreCase(host)
            && path != null
            && path.startsWith("/admin/mobile.php");
    }

    private void loadLoginFallback(WebView view) {
        fallbackLoaded = true;
        view.post(new Runnable() {
            @Override
            public void run() {
                Toast.makeText(MainActivity.this, "Admin app page is not deployed yet. Opening login.", Toast.LENGTH_LONG).show();
                webView.loadUrl(getString(R.string.admin_fallback_url));
            }
        });
    }

    @Override
    public void onBackPressed() {
        if (webView != null && webView.canGoBack()) {
            webView.goBack();
            return;
        }

        super.onBackPressed();
    }

    private class AdminWebViewClient extends WebViewClient {
        @Override
        public boolean shouldOverrideUrlLoading(WebView view, WebResourceRequest request) {
            Uri uri = request.getUrl();
            if (shouldOpenExternally(uri)) {
                openExternal(uri);
                return true;
            }

            return false;
        }

        @Override
        public boolean shouldOverrideUrlLoading(WebView view, String url) {
            Uri uri = Uri.parse(url);
            if (shouldOpenExternally(uri)) {
                openExternal(uri);
                return true;
            }

            return false;
        }

        @Override
        public void onPageFinished(WebView view, String url) {
            super.onPageFinished(view, url);
            progressBar.setVisibility(View.GONE);
            offlineView.setVisibility(View.GONE);
        }

        @Override
        public void onReceivedHttpError(WebView view, WebResourceRequest request, WebResourceResponse errorResponse) {
            super.onReceivedHttpError(view, request, errorResponse);
            if (request.isForMainFrame() && shouldFallbackToLogin(request.getUrl().toString(), errorResponse.getStatusCode())) {
                loadLoginFallback(view);
            }
        }
    }

    private class AdminChromeClient extends WebChromeClient {
        @Override
        public void onProgressChanged(WebView view, int newProgress) {
            progressBar.setVisibility(newProgress >= 100 ? View.GONE : View.VISIBLE);
            progressBar.setProgress(newProgress);
        }

        @Override
        public boolean onJsAlert(WebView view, String url, String message, JsResult result) {
            new AlertDialog.Builder(MainActivity.this)
                .setMessage(message)
                .setPositiveButton(android.R.string.ok, new DialogInterface.OnClickListener() {
                    @Override
                    public void onClick(DialogInterface dialog, int which) {
                        result.confirm();
                    }
                })
                .setOnCancelListener(new DialogInterface.OnCancelListener() {
                    @Override
                    public void onCancel(DialogInterface dialog) {
                        result.cancel();
                    }
                })
                .show();
            return true;
        }
    }

    public class AdminBridge {
        @JavascriptInterface
        public void notifyNewOrder(final String title, final String body, final String targetUrl) {
            runOnUiThread(new Runnable() {
                @Override
                public void run() {
                    showOrderNotification(title, body, targetUrl);
                }
            });
        }
    }
}
