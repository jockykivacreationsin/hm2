(function ($) {
  'use strict';

  $(window).on('load', function () {
    $(".codex-loader").fadeOut("slow");
  });

  $(document).ready(function () {

    //***- SIDEBAR - START ***//
    $(".submenu-list").slideUp(300);
    $(".secondsubmenu-list").slideUp(300);
    $('.codex-menu .menu-item').click(function () {
      $(this).children(".submenu-list").slideToggle(300);
      $(this).siblings().children(".submenu-list").slideUp(300);
    });
    var menuUrl = window.location.pathname.split('/');
    $(".codex-menu .menu-item a").each(function () {
      if ($(this).attr("href")) {
        if (menuUrl[menuUrl.length - 1] == $(this).attr("href")) {
          $(this).addClass("active");
          $(this).parent().addClass("active");
          $(this).parent().parent().parent().addClass("active");
        }
      }
    });

    $('.sidebar-action').click(function () {
      $('body').toggleClass("daactive-sidebar");
    });
    $('.codex-header .navsearch-bar .input-group-text').click(function () {
      $('.codex-header .navsearch-bar .form-control').toggleClass("active");
    });

    $('.cdxapp-toggle').click(function () {
      $('.cdxapp-sidebar ,.cdxapp-xl-sidebar').toggleClass('show-sidebar')
    });

    //*** HEADER START ***//
    $(".header-menu .cdxaction-menu,.header-menu ul.menu-list >li >a").click(function () {
      $(this).siblings('.menu-list,.sub-list').toggleClass("open");
      $(this).parent('.menu-item').siblings('.menu-item').children('.sub-list').removeClass("open");
    });   

    //*** Dropdown Action ***//
    $(".action-menu .action-toggle").click(function () {
      $(this).next('.action-dropdown').toggleClass('active');
    });

    //*** Authentication ***//
    $('.toggle-show').click(function () {
      var inp = $('.showhide-password');
      if (inp.attr('type') == 'password') {
        setTimeout(function () {
          inp.attr('type', 'text');
          $(".toggle-show").addClass('fa-eye-slash');
        }, 250);
      } else {
        setTimeout(function () {
          inp.attr('type', 'password');;
          $(".toggle-show").removeClass('fa-eye-slash');
        }, 250);
      }
    });   

    //*** Window Full Screen ***//
    $(".btn-windowfull").on("click", function () {
      document.fullScreenElement && null !== document.fullScreenElement || !document.mozFullScreen && !document.webkitIsFullScreen ? document.documentElement.requestFullScreen ? document.documentElement.requestFullScreen() : document.documentElement.mozRequestFullScreen ? document.documentElement.mozRequestFullScreen() : document.documentElement.webkitRequestFullScreen && document.documentElement.webkitRequestFullScreen(Element.ALLOW_KEYBOARD_INPUT) : document.cancelFullScreen ? document.cancelFullScreen() : document.mozCancelFullScreen ? document.mozCancelFullScreen() : document.webkitCancelFullScreen && document.webkitCancelFullScreen()
    });

    //*** Tooltip Generator - START ***//
    $('.iconGroup li i, .iconGroup li svg').each(function () {
      let className = $(this).attr('class');
      $(this).attr('data-bs-toggle', 'tooltip')
      $(this).attr('title', className);
    });

    //*** Theme Icon Code Copy JS ***//
    function clipboard_genFun(e) {
      // Create Div For Clipboard
      let $div = $('<div/>').appendTo('body');
      $div.attr('class', 'copied');
      $div.html(e).show().fadeOut(600);
      setTimeout(function () {
        $div.remove();
      }, 600);
    }
    $('.iconGroup').on('click', 'li', function () {
      let iconName;
      let html;
      if ($('.iconGroup').hasClass('svg')) {
        iconName = $(this).children('span').attr('class');
        html = `<i data-feather="${iconName}"></i>`;
      } else {
        iconName = $(this).children('i').attr('class');
        html = `<i class="${iconName}"></i>`;
      }
      // Create Input For ClassName
      let $temp = $("<input>");
      $("body").append($temp);
      $temp.val(html).select();
      document.execCommand("copy");
      $temp.remove();

      // Clipboard
      clipboard_genFun('Copied to clipboard');
    });

    //*** - Tooltip-JS ***//
    $('[data-bs-toggle="tooltip"]').tooltip();

    //*** SINGLE NUMBER COUNTER ***//
    $('.counter').each(function () {
      var $this = $(this);
      jQuery({ Counter: 0 }).animate({ Counter: $this.text() }, {
        duration: 3000,
        easing: 'swing',
        step: function () {
          var num = Math.ceil(this.Counter).toString();
          if (Number(num) > 999) {
            while (/(\d+)(\d{3})/.test(num)) {
              num = num.replace(/(\d+)(\d{3})/, '$1' + ',' + '$2');
            }
          }
          $this.text(num);
        }
      });
    });

    //** POPOVER JS **//
    var popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'))
    var popoverList = popoverTriggerList.map(function (popoverTriggerEl) {
      return new bootstrap.Popover(popoverTriggerEl)
    })

    //*** PROGESSBAR COUNTER WITH NUMBER
    $('.progressCounter').each(function () {
      var bar = $(this).find('.progress-bar');
      var value = $(this).find('.count');
      bar.prop('Counter', 0).animate({
        Counter: parseFloat(bar.attr('aria-valuenow'))
      },
        {
          duration: 2000,
          easing: 'swing',
          step: function (now) {
            var number = parseFloat(Math.round(now * 100) / 100).toFixed(2);
            bar.css({ 'width': number + '%' });
          }
        });
      jQuery({ Counter: 0 }).animate({ Counter: value.text() }, {
        duration: 2000,
        easing: 'swing',
        step: function (num) {
          var num = Math.ceil(this.Counter).toString();
          if (Number(num) > 999) {
            while (/(\d+)(\d{3})/.test(num)) {
              num = num.replace(/(\d+)(\d{3})/, '$1' + ',' + '$2');
            }
          }
          value.text(num);
        }
      });
    });

  });

  $(document).on("click", function (event) {
    //*** Action Menu
    var $trigger = $(".action-menu,.action-toggle,.action-dropdown");
    if ($trigger !== event.target && !$trigger.has(event.target).length) {
      $(".action-dropdown").removeClass("active");
    }
  });

  $(document).on("click", function (event) {
    //***Action Menu
    var $trigger = $(".cdxaction-menu, ul.menu-list, .header-menu");
    if ($trigger !== event.target && !$trigger.has(event.target).length) {
      $(".menu-list").removeClass("open");
    }
  });

  //*** BACK TO TOP START ***//
  $(window).scroll(function() {
    if ($(window).scrollTop() > 450) {
      $('.scroll-top').addClass('show');
    } else {
      $('.scroll-top').removeClass('show');
    } 
  });
  $(document).ready(function(){
    $(document).on("click",'.scroll-top',function(){    
      $('html, body').animate({scrollTop:0}, '450');
    });
  });

}(jQuery))