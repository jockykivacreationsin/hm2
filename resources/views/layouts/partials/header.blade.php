@php
  $permissionService = app(\App\Services\PermissionService::class);
@endphp
  <!-- Header Start-->
  <header class="codex-header">
    <div class="flex-between">
      <div class="header-left d-flex align-items-center">
        <div class="sidebar-action navicon-wrap"><i data-feather="menu"></i></div>
        <div class="navsearch-bar">
          <div class="hnl-box">
            <img class="hn-logo" src="{{ asset('assets/images/hlogo.png') }}"><h3 class="d-none d-md-inline">{{ $hospitalName ?? '' }}</h3>
          </div>
        </div>
        <div class="navsearch-bar hidden">
          <div class="input-group">
            <input class="form-control" type="text" value="" placeholder="Search Patient....."><span class="input-group-text"><i data-feather="search"></i></span>
          </div>
        </div>

      </div>
      <div class="header-right">
        <ul class="nav-iconlist">
          @if($permissionService->hasPermission('b_beds_ui'))
          <li>
            <div class="navicon-wrap"><a href="{{ Route::has('beds.ui') ? route('beds.ui') : '#' }}"><i class="fas fa-procedures"></i></a><div class="noti-count"></div></div>
          </li>
          @endif
        <li>
          <div class="navicon-wrap btn-windowfull"><i data-feather="maximize"></i></div>
        </li>
        <li class="hidden">
          <div class="action-toggle navicon-wrap"><i data-feather="bell"></i>
            <div class="noti-count"></div>
          </div>
          <div class="hover-dropdown navnotification-drop">
            <div class="drop-header">
              <h6>notification<span>05           </span></h6>
            </div>
            <ul class="scroll-primary" data-simplebar="">
              <li>
                <div class="media">
                  <div class="badgeavtar bg-success">A</div>
                  <div class="media-body ml-10">
                    <h6>Successful Paid Invoice #1579</h6><span class="text-light">20 mins ago</span>
                  </div>
                </div>
              </li>
              <li>
                <div class="media">
                  <div class="badgeavtar bg-warning"> <i class="fa fa-clock-o"></i></div>
                  <div class="media-body ml-10">
                    <h6>Confirmed Dr Aleni Joshep visit</h6><span class="text-light">2 hours ago</span>
                  </div>
                </div>
              </li>
              <li>
                <div class="media">
                  <div class="badgeavtar bg-info"> <i class="fa fa-times"></i></div>
                  <div class="media-body ml-10">
                    <h6>Cancel Appointment at 5:00 PM</h6><span class="text-light">2 hours ago</span>
                  </div>
                </div>
              </li>
              <li>
                <div class="media">
                  <div class="badgeavtar bg-success">A</div>
                  <div class="media-body ml-10">
                    <h6>Successful Paid Invoice #1215</h6><span class="text-light">3 hours ago</span>
                  </div>
                </div>
              </li>
              <li>
                <div class="media">
                  <div class="badgeavtar bg-secondary"><i class="fa fa-check"></i></div>
                  <div class="media-body ml-10">
                    <h6>Great Speed Notify of 2.5 LTC</h6><span class="text-light">4 hours ago</span>
                  </div>
                </div>
              </li>
            </ul>
            <div class="drop-footer"><a href="javascript:void(0)">See All Notification</a></div>
          </div>
        </li>
        <li class="hidden">
          <div class="navicon-wrap"><i data-feather="mail"></i>
            <div class="noti-count"></div>
          </div>
          <div class="hover-dropdown navmail-drop">
            <div class="drop-header">
              <h6>Mail<span>08           </span></h6>
            </div>
            <ul class="scroll-primary" data-simplebar="">
              @foreach([
                [2, 'Anna Mull', '20 mins ago'],
                [3, 'Hal Appeno', '30 mins ago'],
                [4, 'Paul Molive', '45 mins ago'],
                [5, 'Saul T. Balls', '50 mins ago'],
                [6, 'Anna Mull', '1 hour ago'],
                [7, 'William Stephin', '2 hour ago'],
              ] as [$avatar, $mailName, $mailAgo])
              <li>
                <div class="media">
                  <div class="avtar-wrap"><img class="img-fluid" src="{{ asset('assets/images/avtar/' . $avatar . '.jpg') }}" alt=""></div>
                  <div class="media-body ml-10">
                    <h6>{{ $mailName }}<span class="text-light ml-10 fs-10">- {{ $mailAgo }}</span></h6><span class="text-light">Successful Paid Invoice #1579</span>
                  </div>
                </div>
              </li>
              @endforeach
            </ul>
            <div class="drop-footer"><a href="javascript:void(0)">See All Messages</a></div>
          </div>
        </li>
        <li class="nav-profile"><a class="action-toggle" href="javascript:void(0)">
          <div class="media">
            <div class="user-icon"><img class="img-fluid rounded-50" src="{{ $userPhoto }}" alt=""></div>
          </div></a>
          <div class="hover-dropdown navprofile-drop">
            <div id="role_id_{{ session('user_role_id', 0) }}" class="login-name">{{ session('display_name') }}</div>
            <ul>
              <li><a href="{{ route('settings.index') }}"> <i class="ti-settings"></i>Setting</a></li>
              <li>
                {{-- POST + CSRF: a GET link could be triggered by any other page (an img tag pointing at /logout would sign staff out). --}}
                <form method="POST" action="{{ route('logout') }}">
                  @csrf
                  <a href="javascript:void(0)" onclick="this.closest('form').submit()"><i class="fa fa-sign-out"></i>log out</a>
                </form>
              </li>
            </ul>
          </div>
        </li>
      </ul>
    </div>

  </div>
  <div class="row top-rq-alert">
    <div class="col-md-12">
      @if($noDoctors)
        <div class="alert alert-danger alert-dismissible fade show" role="alert">Please Add Doctors <a class="alert-action-btn" href="{{ Route::has('staff.index') ? route('staff.index') : '#' }}">Add Doctor</a>
          <button class="btn-close" type="button" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
      @endif
    </div>
  </div>
</header>
