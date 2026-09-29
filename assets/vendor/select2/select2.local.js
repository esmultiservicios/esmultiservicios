/*! Local Select2-compatible single-select enhancer for ES MULTISERVICIOS. No CDN. */
(function($){
  'use strict';
  if (!$ || $.fn.select2) return;

  function Select2Local($select, options){
    this.$select = $select;
    this.el = $select[0];
    this.options = $.extend({width:'100%', minimumResultsForSearch:8}, options || {});
    this.opened = false;
    this.build();
    this.bind();
    this.sync();
  }

  Select2Local.prototype.build = function(){
    var self=this;
    this.$container = $('<span class="select2 select2-container select2-container--default es-select2" aria-hidden="false"></span>');
    this.$selection = $('<span class="selection"></span>');
    this.$single = $('<span class="select2-selection select2-selection--single" role="combobox" aria-haspopup="true" aria-expanded="false" tabindex="0"></span>');
    this.$rendered = $('<span class="select2-selection__rendered"></span>');
    this.$arrow = $('<span class="select2-selection__arrow" aria-hidden="true"><b></b></span>');
    this.$single.append(this.$rendered, this.$arrow);
    this.$selection.append(this.$single);
    this.$container.append(this.$selection);
    this.$select.after(this.$container).addClass('select2-hidden-accessible').attr('aria-hidden','true').attr('tabindex','-1');
    this.$container.css('width', this.options.width || '100%');

    this.$dropdown = $('<span class="select2-container select2-container--default select2-container--open es-select2-dropdown-host" style="display:none"></span>');
    var $drop = $('<span class="select2-dropdown select2-dropdown--below"></span>');
    this.$searchWrap = $('<span class="select2-search select2-search--dropdown"></span>');
    this.$search = $('<input class="select2-search__field" type="search" autocomplete="off" autocorrect="off" autocapitalize="none" spellcheck="false" role="searchbox">');
    this.$searchWrap.append(this.$search);
    this.$results = $('<span class="select2-results"></span>');
    this.$list = $('<ul class="select2-results__options" role="listbox"></ul>');
    this.$results.append(this.$list);
    $drop.append(this.$searchWrap, this.$results);
    this.$dropdown.append($drop).appendTo(document.body);
    this.renderOptions('');
    if (this.el.options.length < this.options.minimumResultsForSearch) this.$searchWrap.hide();
  };

  Select2Local.prototype.renderOptions = function(term){
    var self=this, needle=(term||'').toLowerCase();
    this.$list.empty();
    Array.prototype.forEach.call(this.el.options,function(opt,idx){
      var text=opt.textContent || opt.innerText || '';
      if (needle && text.toLowerCase().indexOf(needle)===-1) return;
      var $li=$('<li class="select2-results__option" role="option"></li>').text(text).attr('data-index',idx);
      if (opt.disabled) $li.attr('aria-disabled','true').addClass('select2-results__option--disabled');
      if (opt.selected) $li.attr('aria-selected','true').addClass('select2-results__option--selected');
      else $li.attr('aria-selected','false');
      self.$list.append($li);
    });
  };

  Select2Local.prototype.sync = function(){
    var opt=this.el.options[this.el.selectedIndex];
    var text=opt ? (opt.textContent || opt.innerText || '') : '';
    this.$rendered.text(text).attr('title', text);
    this.renderOptions(this.$search.val() || '');
  };

  Select2Local.prototype.position = function(){
    var rect=this.$container[0].getBoundingClientRect();
    var width=Math.max(rect.width, 220);
    var viewportH=window.innerHeight || document.documentElement.clientHeight;
    var estimated=Math.min(360, Math.max(160, this.el.options.length*42 + (this.$searchWrap.is(':visible')?54:0)));
    var openAbove=(viewportH-rect.bottom < estimated && rect.top > estimated);
    this.$dropdown.css({position:'fixed',left:Math.max(8,Math.min(rect.left,window.innerWidth-width-8))+'px',width:Math.min(width,window.innerWidth-16)+'px',zIndex:10050});
    var $drop=this.$dropdown.find('.select2-dropdown');
    $drop.toggleClass('select2-dropdown--above',openAbove).toggleClass('select2-dropdown--below',!openAbove);
    if(openAbove){ this.$dropdown.css({top:'auto',bottom:(viewportH-rect.top)+'px'}); }
    else { this.$dropdown.css({top:rect.bottom+'px',bottom:'auto'}); }
  };

  Select2Local.prototype.open = function(){
    if(this.opened || this.el.disabled) return;
    $('.es-select2').each(function(){ var inst=$(this).prev('select').data('select2-local'); if(inst && inst!==this) inst.close(); });
    this.opened=true;
    this.$container.addClass('select2-container--open');
    this.$single.attr('aria-expanded','true');
    this.position();
    this.$dropdown.show();
    this.renderOptions('');
    if(this.$searchWrap.is(':visible')) setTimeout(()=>this.$search.trigger('focus'),0);
  };

  Select2Local.prototype.close = function(){
    if(!this.opened) return;
    this.opened=false;
    this.$container.removeClass('select2-container--open');
    this.$single.attr('aria-expanded','false');
    this.$dropdown.hide();
    this.$search.val('');
  };

  Select2Local.prototype.bind = function(){
    var self=this;
    this.$single.on('click',function(e){e.preventDefault(); self.opened?self.close():self.open();});
    this.$single.on('keydown',function(e){
      if(e.key==='Enter'||e.key===' '||e.key==='ArrowDown'){e.preventDefault();self.open();}
      if(e.key==='Escape'){self.close();}
    });
    this.$search.on('input',function(){self.renderOptions(this.value);});
    this.$list.on('click','.select2-results__option:not(.select2-results__option--disabled)',function(){
      var idx=parseInt($(this).attr('data-index'),10);
      if(Number.isNaN(idx)) return;
      self.el.selectedIndex=idx;
      self.$select.trigger('input').trigger('change');
      self.sync(); self.close(); self.$single.trigger('focus');
    });
    this.$select.on('change.select2local',function(){self.sync();});
    $(document).on('mousedown.select2local',function(e){if(self.opened && !self.$container[0].contains(e.target) && !self.$dropdown[0].contains(e.target)) self.close();});
    $(window).on('resize.select2local scroll.select2local',function(){if(self.opened) self.position();});
    var form=this.el.form;
    if(form) form.addEventListener('reset',function(){setTimeout(function(){self.sync();self.close();},0);});
  };

  $.fn.select2 = function(options){
    return this.each(function(){
      var $el=$(this), inst=$el.data('select2-local');
      if(typeof options==='string'){
        if(inst && options==='destroy'){inst.close();inst.$dropdown.remove();inst.$container.remove();$el.removeClass('select2-hidden-accessible').removeAttr('aria-hidden tabindex').removeData('select2-local');}
        return;
      }
      if(!inst){inst=new Select2Local($el,options);$el.data('select2-local',inst);}
    });
  };
})(window.jQuery);
