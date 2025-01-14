;

const _location_search = new URLSearchParams(window.location.search);
/**
 * The icon map for file extension
 * Notice: The map key should has the fontawesome icon like `fa-file-${v}`
 */
const ext2Icon = {
    audio: ["flac", "aac", "wav", "mp3"],
    video: ["mkv", "mka", "mp4"],
    image: ["jpg", "bmp", "jpeg", "webp"],
    alt: ["txt", "log", "cue", "ass"],
    archive: ["rar", "zip", "7z"],
    word: ["doc", "docx", "docm", "dotx", "dotm", "dot", "odt"],
    powerpoint: ["ppt", "pptx", "pptm", "potx", "potm", "pot", "ppsx", "ppsm", "pps", "ppam", "ppa", "odp"],
    excel: ["xlsx", "xlsm", "xlsb", "xls", "xltx", "xltm", "xlt", "xlam", "xla", "ods"],
    pdf: ["pdf"],
    csv: ["csv"],
    code: [],
    contract: []
};

const paswordStrengthText = {
    0: "Worst ☹",  // too guessable: risky password. (guesses < 10^3)
    1: "Bad ☹",    // too guessable: risky password. (guesses < 10^3)
    2: "Weak ☹",   // somewhat guessable: protection from unthrottled online attacks. (guesses < 10^8)
    3: "Good ☺",   // safely unguessable: moderate protection from offline slow-hash scenario. (guesses < 10^10)
    4: "Strong ☻"  // very unguessable: strong protection from offline slow-hash scenario. (guesses >= 10^10)
};

function humanFileSize(bytes, fix, si) {
    let thresh = si ? 1000 : 1024;
    if (Math.abs(bytes) < thresh) {
        return bytes + ' B';
    }
    let units = si
        ? ['kB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB']
        : ['KiB', 'MiB', 'GiB', 'TiB', 'PiB', 'EiB', 'ZiB', 'YiB'];
    let u = -1;
    do {
        bytes /= thresh;
        ++u;
    } while (Math.abs(bytes) >= thresh && u < units.length - 1);
    return bytes.toFixed(fix ? fix : 2) + ' ' + units[u];
}

function location_search_replace(new_params) {
    let search = _location_search;
    for (let i in new_params) {
        search.set(i,new_params[i]);
    }
    return '?' + search.toString();
}

function get_ext_icon (ext) {
    for (let type in ext2Icon) {
        if (ext2Icon[type].indexOf(ext) >= 0) {
            return 'fa-file-' + type;
        }
    }
    return "fa-file";
}

jQuery(document).ready(function() {
    // Drop all support of IE 6-11
    if ($.zui.browser.ie) {
        $.zui.browser.tip();
    }

    // Declare Const
    const api_point = '/api/v1';

    // Active tooltop
    $('[data-toggle="tooltip"]').tooltip();

    // Active Pager which source from remote
    $('ul[data-ride="remote_pager"]').pager({
        page: _location_search.get('page') || 0,
        maxNavCount: 8,
        elements: ['first_icon', 'prev_icon', 'pages', 'next_icon', 'last_icon'],
        linkCreator: function(page, pager) {
            return location_search_replace({
                'page': page,
                'limit': pager.recPerPage
            });
        }
    });
    
    // Captcha Img Re-flush
    let captcha_img_another = $('.captcha_img');
    captcha_img_another.on('click',function () {
        $(this).attr('src','/captcha?t=' + Date.now())  // Change src to get another captcha image
            .parent('.captcha_img_load').addClass('load-indicator loading');  // Add loading indicator in parent of img tag
    });
    captcha_img_another.on('load',function () {
        $(this).parent('.captcha_img_load').removeClass('load-indicator loading');
    });

    
    
    // TODO Add Scroll to TOP fixbar



    // Common Function
    function create_error_notice(text,option) {
        option = $.extend({
            icon: 'exclamation-sign',
            type: 'danger',
            placement: 'top-right'
        },option);
        return new $.zui.Messager(text, option).show();
    }

    // Password strength checker
    let password_strength = $('#password_strength');
    if (password_strength.length > 0) {
        let strength_text = $('#password_strength_text');
        let strength_suggest = $('#password_strength_suggest');
        $('#password').on('input', function () {
            let val = $(this).val();
            if (val !== "") {
                try {
                    let result = zxcvbn(val);
                    password_strength.show();
                    strength_text.html(paswordStrengthText[result.score]);
                    strength_suggest.html( (result.feedback.warning !== "" ? (result.feedback.warning + "<br>") : "") + result.feedback.suggestions);
                } catch (e) {
                }
            } else {
                password_strength.hide();
                strength_suggest.text('');
            }
        })
    }

    $('#password_help_btn').click(function () {
        let password_input = $(this).prev('input[name="password"]');
        let help_info = $(this).children('i');
        let old_type_is_password = password_input.attr('type') === 'password';
        password_input.attr('type', old_type_is_password ? 'text' : 'password');
        if (old_type_is_password) {
            help_info.removeClass('fa-eye').addClass('fa-eye-slash');
        } else {
            help_info.removeClass('fa-eye-slash').addClass('fa-eye');
        }
    });
    
    // Torrent favour Add/Remove action
    $('.torrent-favour').click(function () {
        let that = $(this);
        let tid = that.attr('data-tid');
        let star = that.find(' > i');

        $.post(api_point + '/torrent/bookmark', {'tid': tid}, function (res) {
            if (res.success) {
                let old_is_stared = star.hasClass('fas');
                star.toggleClass('fas', !old_is_stared).toggleClass('far', old_is_stared);
                new $.zui.Messager(`Torrent(${tid}) ${res.result} from your favour successfully`, {
                    icon: 'ok-sign',
                    type: 'success',
                    placement: 'top-right'
                }).show();
            } else {
                create_error_notice(res.errors.join(', '));
            }
        });
    });

    // View Torrent File list
    $('.torrent-files').click(function () {
        let that = $(this);
        let tid = that.attr('data-tid');

        function list_worker(tree, par = '') {
            let ret = '';
            let size = 0;
            for (let k in tree) {
                let v = tree[k];
                if (typeof v == 'object') {
                    let [in_ret, in_size] = list_worker(v, par + "/" + k);
                    ret += `<li${par === '' ? ' class="open"' : ''}><a href="#">${k} (<span class="file-size" data-size="${v}">${humanFileSize(in_size)}</span>)</a><ul>${in_ret}</ul></li>`;
                    size += in_size;
                } else {
                    let ext = k.substr(k.lastIndexOf('.') + 1).toLowerCase();

                    ret += `<li><i class="fa ${get_ext_icon(ext)} fa-fw"></i> ${k} (<span class="file-size" data-size="${v}">${humanFileSize(v)}</span>)</li>`;
                    size += v;
                }
            }
            return [ret, size];
        }

        // TODO Add Client Cache ( innodb )
        $.get(api_point + '/torrent/filelist', {'tid': tid}, function (res) {
            if (res.success) {
                let file_list = res.result;
                (new $.zui.ModalTrigger({
                    name: 'torrent_filelist_model',
                    showHeader: false,
                    size: 'lg',
                    //width: '700px',
                    moveable: true,
                    custom: "<ul  class='tree tree-lines tree-folders' data-ride='tree' id='torrent_filelist'>" + list_worker(file_list)[0] + "</ul>"
                })).show({
                    shown:function () {
                        $('#torrent_filelist').tree();
                    }
                });
            } else {
                create_error_notice(res.errors.join(', '));
            }
        });
    });

    // For torrents structure page
    if ($('#torrent_structure').length) {
        $('#torrent_structure div.dictionary,div.list').click(function () {
            $(this).next('ul').toggle();
        });
    }

    // Show Extend debug info of Database sql execute and Redis key hit
    if (typeof _extend_debug_info !== 'undefined' && _extend_debug_info) {
        $('#extend_debug_info').modalTrigger({
            size: 'lg',
            custom: function () {
                let ret = '';
                let parsed_sql_data = JSON.parse(_sql_data || '[]');
                let parsed_redis_data = JSON.parse(_redis_data || '{}');
                ret += '<b>SQL query list:</b><ul>';
                $.each(parsed_sql_data,function (i,v) {
                    ret += `<li><code>${v}</code></li>`;
                });
                ret += '</ul>';
                ret += '<b>Redis keys hit: (Some keys hit may not appear here)</b><ul>';
                $.each(parsed_redis_data,function (k, v) {
                    ret += '<li><code>' + k + "</code> : " + v + '</li>';
                });
                ret += '</ul>';
                return ret;
            }});
    }
});
