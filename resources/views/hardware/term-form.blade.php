<div id="term-form-root"
     data-status-url="{{ route('terms.status', $asset->id) }}"
     style="padding: 4px 8px;">

    <div id="term-loading" class="text-center" style="padding: 40px 0;">
        <i class="fa fa-spinner fa-spin fa-2x text-muted"></i>
        <p class="text-muted" style="margin-top: 10px; font-size: 13px;">
            {{ trans('general.loading') }}
        </p>
    </div>

    <div id="term-content" style="display:none;"></div>
</div>

<style>
.term-item {
    border: 1px solid #e0e0e0;
    border-radius: 6px;
    padding: 14px 16px;
    margin-bottom: 10px;
    background: #fff;
    transition: box-shadow .15s;
}
.term-item:hover { box-shadow: 0 2px 8px rgba(0,0,0,.08); }
.term-item.is-blocked { background: #fafafa; opacity: .85; }
.term-item.is-generated { border-left: 4px solid #5cb85c; }
.term-item.is-blocked   { border-left: 4px solid #d9534f; }
.term-item.is-available { border-left: 4px solid #337ab7; }

.term-name {
    font-weight: 600;
    font-size: 14px;
    color: #333;
    margin-bottom: 4px;
}
.term-meta {
    font-size: 11px;
    color: #888;
    margin-bottom: 10px;
    line-height: 1.5;
}
.term-badge {
    display: inline-block;
    font-size: 10px;
    font-weight: 600;
    padding: 2px 7px;
    border-radius: 3px;
    margin-right: 4px;
    margin-bottom: 6px;
    text-transform: uppercase;
    letter-spacing: .4px;
}
.badge-generated { background: #dff0d8; color: #3c763d; }
.badge-blocked   { background: #f2dede; color: #a94442; }
.badge-available { background: #d9edf7; color: #31708f; }
.badge-type      { background: #f5f5f5; color: #555; border: 1px solid #ddd; }

.term-blocked-msg {
    font-size: 12px;
    color: #a94442;
    margin-top: 6px;
    margin-bottom: 0;
}
.term-log-info {
    font-size: 11px;
    color: #888;
    margin-top: 5px;
    display: block;
}
</style>

<script>
(function () {
    var root       = document.getElementById('term-form-root');
    var statusUrl  = root.getAttribute('data-status-url');
    var loading    = document.getElementById('term-loading');
    var content    = document.getElementById('term-content');
    var generateUrl;

    var i18n = {
        generated:        '{{ trans("general.term_status_generated") }}',
        blocked:          '{{ trans("general.term_status_blocked") }}',
        available:        '{{ trans("general.term_status_available") }}',
        regenerate:       '{{ trans("general.regenerate") }}',
        generate:         '{{ trans("general.generate") }}',
        blocked_by:       '{{ trans("general.term_blocked_by") }}',
        generated_at:     '{{ trans("general.generated_at") }}',
        by:               '{{ trans("general.by") }}',
        no_templates:     '{{ trans("general.no_templates_for_category") }}',
        load_error:       '{{ trans("general.term_load_error") }}',
    };

    function buildItem(t) {
        var stateClass, badgeHtml, actionHtml;

        var typeBadge = '<span class="term-badge badge-type">' + t.term_type + '</span>';

        if (!t.can_generate) {
            stateClass = 'is-blocked';
            badgeHtml  = '<span class="term-badge badge-blocked">' + i18n.blocked + '</span>' + typeBadge;
            actionHtml = '<button class="btn btn-default btn-block btn-sm" disabled style="cursor:not-allowed;">' +
                         '<i class="fa fa-lock"></i> ' + t.name + '</button>' +
                         '<p class="term-blocked-msg"><i class="fa fa-exclamation-triangle"></i> ' +
                         i18n.blocked_by + ': <strong>' + (t.blocked_by || t.depends_on) + '</strong></p>';

        } else if (t.generated) {
            stateClass = 'is-generated';
            badgeHtml  = '<span class="term-badge badge-generated">' + i18n.generated + '</span>' + typeBadge;
            actionHtml = buildForm(t.id, '<i class="fa fa-refresh"></i> ' + i18n.regenerate + ': ' + t.name, 'btn-warning');

        } else {
            stateClass = 'is-available';
            badgeHtml  = '<span class="term-badge badge-available">' + i18n.available + '</span>' + typeBadge;
            actionHtml = buildForm(t.id, '<i class="fa fa-file-text-o"></i> ' + i18n.generate + ': ' + t.name, 'btn-primary');
        }

        var logHtml = '';
        if (t.last_log) {
            logHtml = '<span class="term-log-info">' +
                      '<i class="fa fa-clock-o"></i> ' + i18n.generated_at + ': ' +
                      t.last_log.generated_at + ' &mdash; ' + i18n.by + ': ' +
                      t.last_log.generated_by + '</span>';
        }

        return '<div class="term-item ' + stateClass + '">' +
               '<div>' + badgeHtml + '</div>' +
               '<div class="term-name">' + t.name + '</div>' +
               '<div class="term-meta">' + (t.depends_on ? '{{ trans("general.term_requires") }}: ' + (t.blocked_by || t.depends_on) : '') + '</div>' +
               actionHtml + logHtml +
               '</div>';
    }

    function buildForm(templateId, label, btnClass) {
        var token = (document.querySelector('meta[name="csrf-token"]') || {}).getAttribute('content') || '';
        return '<form method="POST" action="' + generateUrl + '" style="margin-bottom: 4px;">' +
               '<input type="hidden" name="_token" value="' + token + '">' +
               '<input type="hidden" name="term_template_id" value="' + templateId + '">' +
               '<button type="submit" class="btn ' + btnClass + ' btn-block btn-sm">' + label + '</button>' +
               '</form>';
    }

    fetch(statusUrl, {
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') || {}).getAttribute('content') || ''
        }
    })
    .then(function (r) {
        if (!r.ok) throw new Error(r.status);
        return r.json();
    })
    .then(function (data) {
        generateUrl = data.generate_url;

        if (!data.templates || data.templates.length === 0) {
            loading.innerHTML = '<p class="text-muted text-center" style="padding: 20px 0;">' +
                                '<i class="fa fa-info-circle"></i> ' + i18n.no_templates + '</p>';
            return;
        }

        // Ordenação: available → generated → blocked
        var order = { 'is-available': 0, 'is-generated': 1, 'is-blocked': 2 };
        var sorted = data.templates.slice().sort(function (a, b) {
            var stateA = !a.can_generate ? 'is-blocked' : (a.generated ? 'is-generated' : 'is-available');
            var stateB = !b.can_generate ? 'is-blocked' : (b.generated ? 'is-generated' : 'is-available');
            return (order[stateA] || 0) - (order[stateB] || 0);
        });

        var html = sorted.map(buildItem).join('');

        loading.style.display = 'none';
        content.innerHTML     = html;
        content.style.display = 'block';
    })
    .catch(function (e) {
        loading.innerHTML = '<p class="text-danger text-center" style="padding:20px 0;">' +
                            '<i class="fa fa-times-circle"></i> ' + i18n.load_error + '</p>';
        console.error(e);
    });
}());
</script>