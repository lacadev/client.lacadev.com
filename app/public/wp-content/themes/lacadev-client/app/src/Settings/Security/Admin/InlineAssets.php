<?php

namespace App\Settings\Security\Admin;

class InlineAssets
{
    public function render(): void
    {
        ?>
        <style>
        .laca-scan-clean { padding:14px 18px;background:#f0fdf4;border:1px solid #86efac;border-radius:8px;color:#166534;font-size:14px; }
        .laca-scan-summary { padding:10px 16px;background:#fef3c7;border:1px solid #fcd34d;border-radius:6px;margin-bottom:12px; }
        #audit-result .audit-score-circle { display:inline-block;width:80px;height:80px;border-radius:50%;text-align:center;line-height:80px;font-size:22px;font-weight:700;border:4px solid;margin-right:16px; }
        </style>
        <script>
        (function($){
            var ajaxUrl = lacaSecurity.ajaxUrl;
            var nonce   = lacaSecurity.nonce;

            $('#btn-run-audit').on('click', function(){
                $('#audit-progress').show();
                $('#audit-result').empty();
                $.post(ajaxUrl, { action:'laca_security_audit', nonce }, function(res){
                    $('#audit-progress').hide();
                    if (!res.success) { $('#audit-result').html('<p style="color:red;">'+res.data+'</p>'); return; }
                    var d = res.data;
                    var color = d.score >= 80 ? '#16a34a' : d.score >= 50 ? '#d97706' : '#dc2626';
                    var verdict = d.score >= 80 ? 'Tốt' : d.score >= 50 ? 'Trung bình' : 'Yếu';
                    var html = '<div style="display:flex;align-items:center;margin-bottom:20px;">';
                    html += '<div class="audit-score-circle" style="color:'+color+';border-color:'+color+';">'+d.score+'</div>';
                    html += '<div><strong style="font-size:18px;">'+verdict+'</strong><br>';
                    html += '<span style="font-size:13px;color:#666;">Pass: '+d.pass+' | Fail: '+d.fail+' | Warning: '+d.warn+'</span></div></div>';

                    $.each(d.groups, function(cat, checks){
                        html += '<h3 style="margin:16px 0 8px;border-bottom:1px solid #e5e7eb;padding-bottom:6px;">'+cat+'</h3>';
                        html += '<table class="wp-list-table widefat striped" style="margin-bottom:8px;"><tbody>';
                        $.each(checks, function(_, c){
                            var icon = c.status==='pass'?'✓':c.status==='fail'?'✗':c.status==='warn'?'⚠':'ℹ';
                            var col  = c.status==='pass'?'#166534':c.status==='fail'?'#dc2626':c.status==='warn'?'#b45309':'#1e40af';
                            html += '<tr><td style="width:24px;font-size:16px;color:'+col+';">'+icon+'</td>';
                            html += '<td><strong>'+c.title+'</strong><br><span style="font-size:12px;color:#555;">'+c.desc+'</span></td>';
                            html += '<td style="width:60px;text-align:right;font-size:12px;color:#999;">+'+c.points+'pt</td></tr>';
                        });
                        html += '</tbody></table>';
                    });
                    $('#audit-result').html(html);
                });
            });

            $('#btn-fim-scan').on('click', function(){
                $(this).prop('disabled',true).text('Đang quét...');
                $('#fim-progress').show();
                $('#fim-result').empty();
                $.post(ajaxUrl, { action:'laca_fim_scan', nonce }, function(res){
                    $('#btn-fim-scan').prop('disabled',false).text(res.data&&res.data.is_init?'📸 Tạo baseline':'🔍 So sánh với baseline');
                    $('#fim-progress').hide();
                    if (!res.success) { $('#fim-result').html('<p style="color:red;">'+res.data+'</p>'); return; }
                    $('#fim-result').html(res.data.html);
                });
            });

            $('#btn-fim-update').on('click', function(){
                if (!confirm('Cập nhật baseline sẽ ghi đè trạng thái file hiện tại. Tiếp tục?')) return;
                $(this).prop('disabled',true).text('Đang cập nhật...');
                $.post(ajaxUrl, { action:'laca_fim_update_baseline', nonce }, function(res){
                    $('#btn-fim-update').prop('disabled',false).text('🔄 Cập nhật baseline');
                    alert(res.success ? res.data.message : 'Lỗi: ' + res.data);
                    if (res.success) location.reload();
                });
            });

            var scanId = null;
            $('#btn-malware-scan').on('click', function(){
                var exts = [];
                $('input[name="scan_ext[]"]:checked').each(function(){ exts.push($(this).val()); });
                if (!exts.length) { alert('Chọn ít nhất 1 loại file.'); return; }
                $(this).prop('disabled',true).text('Đang quét...');
                $('#malware-progress').show();
                $('#malware-progress-bar').css('width','0%');
                $('#malware-progress-text').text('Đang khởi tạo...');
                $('#malware-result').empty();

                $.post(ajaxUrl, { action:'laca_malware_init', nonce, extensions: exts }, function(res){
                    if (!res.success) { finishScan('Lỗi: '+res.data); return; }
                    scanId = res.data.scan_id;
                    var total = res.data.total;
                    $('#malware-progress-text').text('Tìm thấy '+total+' file cần quét.');
                    scanChunk(0, total);
                });
            });

            function scanChunk(offset, total){
                $.post(ajaxUrl, { action:'laca_malware_chunk', nonce, scan_id:scanId, offset }, function(res){
                    if (!res.success) { finishScan('Lỗi khi quét: '+res.data); return; }
                    var d = res.data;
                    var pct = total > 0 ? Math.round((d.scanned/total)*100) : 100;
                    $('#malware-progress-bar').css('width', pct+'%');
                    $('#malware-progress-text').text(d.scanned+' / '+total+' file | Phát hiện: '+d.findings);
                    if (d.done) {
                        getResults();
                    } else {
                        scanChunk(d.next_offset, total);
                    }
                }).fail(function(){ finishScan('Lỗi kết nối.'); });
            }

            function getResults(){
                $.post(ajaxUrl, { action:'laca_malware_result', nonce, scan_id:scanId }, function(res){
                    finishScan(null);
                    if (!res.success) { $('#malware-result').html('<p style="color:red;">'+res.data+'</p>'); return; }
                    $('#malware-result').html(res.data.html);
                });
            }

            function finishScan(errMsg){
                $('#btn-malware-scan').prop('disabled',false).text('🦠 Bắt đầu quét');
                if (errMsg) { $('#malware-progress').hide(); $('#malware-result').html('<p style="color:red;">'+errMsg+'</p>'); }
                else { $('#malware-progress').hide(); }
            }

            $('#btn-user-scan').on('click', function(){
                $(this).prop('disabled',true).text('Đang quét...');
                $('#user-scan-progress').show();
                $('#user-scan-result').empty();
                $.post(ajaxUrl, { action:'laca_hidden_user_scan', nonce }, function(res){
                    $('#btn-user-scan').prop('disabled',false).text('🔍 Quét ngay');
                    $('#user-scan-progress').hide();
                    if (!res.success) { $('#user-scan-result').html('<p style="color:red;">'+res.data+'</p>'); return; }
                    var d = res.data;
                    var html = renderUserScanResult(d);
                    $('#user-scan-result').html(html);
                });
            });

            function renderUserScanResult(d){
                var s = d.summary;
                var html = '<p>DB: <strong>'+s.db_total+'</strong> | Chuẩn: <strong>'+s.standard_query_total+'</strong> | Admin ẩn: <strong style="color:'+(s.hidden_admin_total?'#dc2626':'#16a34a')+';">'+s.hidden_admin_total+'</strong> | User ẩn: <strong>'+s.hidden_site_user_total+'</strong> | Nghi ngờ: <strong>'+s.suspicious_user_total+'</strong></p>';

                if (d.hidden_admins && d.hidden_admins.length) {
                    html += '<h3 style="color:#dc2626;">🚨 Admin ẩn ('+d.hidden_admins.length+')</h3>';
                    html += renderUserTable(d.hidden_admins);
                }
                if (d.suspicious_users && d.suspicious_users.length) {
                    html += '<h3 style="color:#d97706;">⚠️ User nghi ngờ ('+d.suspicious_users.length+')</h3>';
                    html += renderUserTable(d.suspicious_users);
                }
                if (!d.hidden_admins.length && !d.suspicious_users.length) {
                    html += '<div class="laca-scan-clean">✓ Không phát hiện user ẩn hoặc nghi ngờ.</div>';
                }
                if (d.hook_findings && d.hook_findings.length) {
                    html += '<h3>🔗 Hook callbacks ('+d.hook_findings.length+')</h3>';
                    html += '<table class="wp-list-table widefat striped"><thead><tr><th>Hook</th><th>Priority</th><th>Callback</th></tr></thead><tbody>';
                    $.each(d.hook_findings, function(_,h){ html += '<tr><td>'+h.hook+'</td><td>'+h.priority+'</td><td><code>'+h.callback+'</code></td></tr>'; });
                    html += '</tbody></table>';
                }
                return html;
            }

            function renderUserTable(users){
                var html = '<table class="wp-list-table widefat striped"><thead><tr><th>ID</th><th>Login</th><th>Email</th><th>Roles</th><th>Đăng ký</th><th>Flags</th></tr></thead><tbody>';
                $.each(users, function(_,u){
                    html += '<tr>';
                    html += '<td>'+u.id+'</td>';
                    html += '<td><strong>'+u.login+'</strong></td>';
                    html += '<td>'+u.email+'</td>';
                    html += '<td>'+u.roles_label+'</td>';
                    html += '<td>'+u.registered+'</td>';
                    html += '<td><small style="color:#666;">'+u.reasons.join('<br>')+'</small></td>';
                    html += '</tr>';
                });
                html += '</tbody></table>';
                return html;
            }

            $('#btn-save-login').on('click', function(){
                $(this).prop('disabled',true);
                $('#login-save-msg').text('Đang lưu...').css('color','#666');
                $.post(ajaxUrl, {
                    action: 'laca_save_login_settings',
                    nonce,
                    slug:    $('#laca-login-slug').val(),
                    enabled: $('#laca-login-enabled').is(':checked') ? 1 : 0,
                }, function(res){
                    $('#btn-save-login').prop('disabled',false);
                    if (res.success) { $('#login-save-msg').text('✓ '+res.data).css('color','green'); }
                    else             { $('#login-save-msg').text('✗ '+res.data).css('color','red'); }
                });
            });

            $('#btn-save-2fa').on('click', function(){
                $(this).prop('disabled',true);
                $('#2fa-save-msg').text('Đang lưu...').css('color','#666');
                $.post(ajaxUrl, {
                    action:  'laca_save_2fa_settings',
                    nonce,
                    enabled: $('#laca-2fa-master').is(':checked') ? 1 : 0,
                }, function(res){
                    $('#btn-save-2fa').prop('disabled',false);
                    if (res.success) { $('#2fa-save-msg').text('✓ '+res.data).css('color','green'); }
                    else             { $('#2fa-save-msg').text('✗ '+res.data).css('color','red'); }
                });
            });

        }(jQuery));
        </script>
        <?php
    }
}
