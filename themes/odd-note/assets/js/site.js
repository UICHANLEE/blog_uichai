/**
 * Odd Note interactions.
 *
 * Everything here is progressive enhancement: links, controls and articles remain
 * usable when JavaScript is unavailable. The native pointer is intentionally kept.
 */
( () => {
	'use strict';

	const root = document.documentElement;
	root.classList.add( 'has-js' );

	const reducedMotion = window.matchMedia( '(prefers-reduced-motion: reduce)' );
	const finePointer = window.matchMedia( '(hover: hover) and (pointer: fine)' );
	const mobileHeader = window.matchMedia( '(max-width: 860px)' );
	const siteHeader = document.querySelector( '[data-site-header]' );
	const menuButton = document.querySelector( '[data-menu-toggle]' );
	const menuLabel = menuButton?.querySelector( '.menu-toggle__label' );
	const primaryNavigation = document.querySelector( '#site-primary-navigation' );
	const experienceControls = document.querySelector( '[data-experience-controls]' );
	const motionButton = document.querySelector( '[data-motion-toggle]' );
	const motionLabel = document.querySelector( '[data-motion-label]' );
	const motionStatus = document.querySelector( '[data-motion-status]' );
	const moodButton = document.querySelector( '[data-mood-toggle]' );
	const moodLabel = document.querySelector( '[data-mood-label]' );
	const halo = document.querySelector( '[data-pointer-halo]' );
	const haloLabel = document.querySelector( '[data-pointer-label]' );
	const progress = document.querySelector( '[data-reading-progress]' );
	const seoulClock = document.querySelector( '[data-seoul-clock]' );
	const moods = [ 'acid', 'coral', 'violet' ];
	let userMotionOff = readStorage( 'odd-note-motion-off' ) === '1';
	let revealObserver = null;
	let pointerCleanup = null;

	function readStorage( key ) {
		try {
			return window.localStorage.getItem( key );
		} catch ( error ) {
			return null;
		}
	}

	function writeStorage( key, value ) {
		try {
			window.localStorage.setItem( key, value );
		} catch ( error ) {
			// Storage can be unavailable in strict privacy modes; the UI still works.
		}
	}

	function effectsAllowed() {
		return ! reducedMotion.matches && ! userMotionOff;
	}

	function setMenuState( open ) {
		if ( ! siteHeader || ! menuButton ) {
			return;
		}

		const expanded = Boolean( open && mobileHeader.matches );
		siteHeader.classList.toggle( 'is-menu-open', expanded );
		menuButton.setAttribute( 'aria-expanded', String( expanded ) );
		menuButton.setAttribute( 'aria-label', expanded ? '메뉴 닫기' : '메뉴 열기' );
		if ( menuLabel ) {
			menuLabel.textContent = expanded ? 'Close' : 'Menu';
		}
	}

	function bindMobileMenu() {
		if ( ! siteHeader || ! menuButton || ! primaryNavigation ) {
			return;
		}

		menuButton.hidden = false;
		menuButton.addEventListener( 'click', () => {
			setMenuState( menuButton.getAttribute( 'aria-expanded' ) !== 'true' );
		} );

		primaryNavigation.addEventListener( 'click', ( event ) => {
			if ( event.target.closest?.( 'a' ) ) {
				setMenuState( false );
			}
		} );

		document.addEventListener( 'keydown', ( event ) => {
			if ( event.key === 'Escape' && menuButton.getAttribute( 'aria-expanded' ) === 'true' ) {
				setMenuState( false );
				menuButton.focus();
			}
		} );

		document.addEventListener( 'pointerdown', ( event ) => {
			if ( menuButton.getAttribute( 'aria-expanded' ) === 'true' && ! siteHeader.contains( event.target ) ) {
				setMenuState( false );
			}
		}, { passive: true } );

		mobileHeader.addEventListener( 'change', () => setMenuState( false ) );
		setMenuState( false );
	}

	function setMood( mood ) {
		const safeMood = moods.includes( mood ) ? mood : moods[ 0 ];
		document.body.dataset.mood = safeMood;

		if ( moodLabel ) {
			moodLabel.textContent = safeMood.toUpperCase();
		}

		if ( moodButton ) {
			moodButton.setAttribute( 'aria-label', `색상 분위기 바꾸기. 현재 ${ safeMood }` );
		}

		writeStorage( 'odd-note-mood', safeMood );
	}

	function setRevealState() {
		const items = [ ...document.querySelectorAll( '.reveal' ) ];

		if ( revealObserver ) {
			revealObserver.disconnect();
			revealObserver = null;
		}

		if ( ! effectsAllowed() || ! ( 'IntersectionObserver' in window ) ) {
			items.forEach( ( item ) => item.classList.add( 'is-visible' ) );
			return;
		}

		revealObserver = new IntersectionObserver(
			( entries, observer ) => {
				entries.forEach( ( entry ) => {
					if ( entry.isIntersecting ) {
						entry.target.classList.add( 'is-visible' );
						observer.unobserve( entry.target );
					}
				} );
			},
			// A low ratio also reveals tall article bodies on narrow viewports.
			{ rootMargin: '0px 0px -8% 0px', threshold: 0.01 }
		);

		items.forEach( ( item ) => revealObserver.observe( item ) );
	}

	function resetInteractiveTransforms() {
		document.querySelectorAll( '[data-tilt-card]' ).forEach( ( card ) => {
			card.style.removeProperty( '--tilt-x' );
			card.style.removeProperty( '--tilt-y' );
		} );

		document.querySelectorAll( '.magnetic' ).forEach( ( item ) => {
			item.style.removeProperty( '--magnetic-x' );
			item.style.removeProperty( '--magnetic-y' );
		} );

		document.querySelectorAll( '[data-pointer-stage]' ).forEach( ( stage ) => {
			stage.style.removeProperty( '--pointer-x' );
			stage.style.removeProperty( '--pointer-y' );
			stage.classList.remove( 'is-tracking' );
		} );
	}

	function updateMotionState() {
		const allowed = effectsAllowed();
		root.classList.toggle( 'motion-off', ! allowed );
		document.body.classList.toggle( 'motion-enabled', allowed );
		document.body.classList.toggle( 'motion-off', ! allowed );

		if ( motionButton ) {
			motionButton.setAttribute( 'aria-pressed', String( allowed ) );
			motionButton.setAttribute( 'aria-disabled', String( reducedMotion.matches ) );
			motionButton.setAttribute( 'aria-label', '화면 효과' );
			motionButton.title = reducedMotion.matches
				? '운영체제에서 동작 줄이기가 켜져 있습니다'
				: allowed ? '화면 효과 끄기' : '화면 효과 켜기';
		}

		if ( motionLabel ) {
			motionLabel.textContent = reducedMotion.matches ? 'FX OS' : allowed ? 'FX ON' : 'FX OFF';
		}

		if ( motionStatus ) {
			motionStatus.textContent = reducedMotion.matches
				? '운영체제 설정에 따라 화면 효과를 줄였습니다.'
				: '';
		}

		setRevealState();

		if ( allowed && finePointer.matches ) {
			startPointerHalo();
		} else {
			stopPointerHalo();
			resetInteractiveTransforms();
		}
	}

	function startPointerHalo() {
		if ( ! halo || pointerCleanup ) {
			return;
		}

		let targetX = 0;
		let targetY = 0;
		let currentX = 0;
		let currentY = 0;
		let frame = 0;
		let initialized = false;

		const render = () => {
			const deltaX = targetX - currentX;
			const deltaY = targetY - currentY;
			currentX += deltaX * 0.22;
			currentY += deltaY * 0.22;
			halo.style.transform = `translate3d(${ currentX }px, ${ currentY }px, 0) translate3d(-50%, -50%, 0)`;

			if ( Math.abs( deltaX ) > 0.2 || Math.abs( deltaY ) > 0.2 ) {
				frame = window.requestAnimationFrame( render );
			} else {
				frame = 0;
			}
		};

		const move = ( event ) => {
			halo.hidden = false;
			targetX = event.clientX;
			targetY = event.clientY;

			if ( ! initialized ) {
				currentX = targetX;
				currentY = targetY;
				initialized = true;
			}

			halo.classList.add( 'is-visible' );
			if ( ! frame ) {
				frame = window.requestAnimationFrame( render );
			}
		};

		const over = ( event ) => {
			const target = event.target.closest?.( '[data-cursor]' );
			const label = target?.dataset.cursor || '';
			halo.classList.toggle( 'has-label', Boolean( label ) );
			if ( haloLabel ) {
				haloLabel.textContent = label;
			}
		};

		const down = () => halo.classList.add( 'is-pressed' );
		const up = () => halo.classList.remove( 'is-pressed' );
		const pause = () => {
			if ( frame ) {
				window.cancelAnimationFrame( frame );
				frame = 0;
			}
		};
		const leave = () => {
			pause();
			initialized = false;
			halo.classList.remove( 'is-visible', 'is-pressed' );
			halo.hidden = true;
		};
		const visibility = () => {
			if ( document.hidden ) {
				leave();
			}
		};

		document.addEventListener( 'pointermove', move, { passive: true } );
		document.addEventListener( 'pointerover', over, { passive: true } );
		document.addEventListener( 'pointerdown', down, { passive: true } );
		document.addEventListener( 'pointerup', up, { passive: true } );
		document.documentElement.addEventListener( 'mouseleave', leave );
		document.addEventListener( 'visibilitychange', visibility );

		pointerCleanup = () => {
			pause();
			document.removeEventListener( 'pointermove', move );
			document.removeEventListener( 'pointerover', over );
			document.removeEventListener( 'pointerdown', down );
			document.removeEventListener( 'pointerup', up );
			document.documentElement.removeEventListener( 'mouseleave', leave );
			document.removeEventListener( 'visibilitychange', visibility );
		};
	}

	function stopPointerHalo() {
		if ( pointerCleanup ) {
			pointerCleanup();
			pointerCleanup = null;
		}

		if ( halo ) {
			halo.hidden = true;
			halo.classList.remove( 'is-visible', 'is-pressed', 'has-label' );
			halo.style.removeProperty( 'transform' );
		}
	}

	function bindPointerStages() {
		document.querySelectorAll( '[data-pointer-stage]' ).forEach( ( stage ) => {
			let frame = 0;
			let rect = null;
			let nextX = 0;
			let nextY = 0;

			stage.addEventListener( 'pointerenter', () => {
				if ( effectsAllowed() && finePointer.matches ) {
					rect = stage.getBoundingClientRect();
					stage.classList.add( 'is-tracking' );
				}
			} );

			stage.addEventListener( 'pointermove', ( event ) => {
				if ( ! effectsAllowed() || ! finePointer.matches ) {
					return;
				}

				rect = rect || stage.getBoundingClientRect();
				nextX = event.clientX - rect.left;
				nextY = event.clientY - rect.top;

				if ( frame ) {
					return;
				}

				frame = window.requestAnimationFrame( () => {
					if ( ! effectsAllowed() || ! finePointer.matches ) {
						frame = 0;
						return;
					}

					stage.style.setProperty( '--pointer-x', `${ nextX.toFixed( 1 ) }px` );
					stage.style.setProperty( '--pointer-y', `${ nextY.toFixed( 1 ) }px` );
					frame = 0;
				} );
			}, { passive: true } );

			stage.addEventListener( 'pointerleave', () => {
				if ( frame ) {
					window.cancelAnimationFrame( frame );
					frame = 0;
				}
				rect = null;
				stage.style.removeProperty( '--pointer-x' );
				stage.style.removeProperty( '--pointer-y' );
				stage.classList.remove( 'is-tracking' );
			} );
		} );
	}

	function bindMagneticItems() {
		document.querySelectorAll( '.magnetic' ).forEach( ( item ) => {
			let rect = null;
			let frame = 0;
			let pointerX = 0;
			let pointerY = 0;

			item.addEventListener( 'pointerenter', () => {
				if ( effectsAllowed() && finePointer.matches ) {
					rect = item.getBoundingClientRect();
				}
			} );

			item.addEventListener( 'pointermove', ( event ) => {
				if ( ! effectsAllowed() || ! finePointer.matches ) {
					return;
				}

				rect = rect || item.getBoundingClientRect();
				pointerX = event.clientX;
				pointerY = event.clientY;

				if ( frame ) {
					return;
				}

				frame = window.requestAnimationFrame( () => {
					if ( ! effectsAllowed() || ! finePointer.matches ) {
						frame = 0;
						return;
					}

					const x = ( pointerX - rect.left - rect.width / 2 ) * 0.12;
					const y = ( pointerY - rect.top - rect.height / 2 ) * 0.12;
					item.style.setProperty( '--magnetic-x', `${ Math.max( -6, Math.min( 6, x ) ) }px` );
					item.style.setProperty( '--magnetic-y', `${ Math.max( -6, Math.min( 6, y ) ) }px` );
					frame = 0;
				} );
			}, { passive: true } );

			item.addEventListener( 'pointerleave', () => {
				if ( frame ) {
					window.cancelAnimationFrame( frame );
					frame = 0;
				}
				rect = null;
				item.style.removeProperty( '--magnetic-x' );
				item.style.removeProperty( '--magnetic-y' );
			} );
		} );
	}

	function bindTiltCards() {
		document.querySelectorAll( '[data-tilt-card]' ).forEach( ( card ) => {
			let rect = null;
			let frame = 0;
			let pointerX = 0;
			let pointerY = 0;

			card.addEventListener( 'pointerenter', () => {
				if ( effectsAllowed() && finePointer.matches && window.innerWidth > 620 ) {
					rect = card.getBoundingClientRect();
				}
			} );

			card.addEventListener( 'pointermove', ( event ) => {
				if ( ! effectsAllowed() || ! finePointer.matches || window.innerWidth <= 620 ) {
					return;
				}

				rect = rect || card.getBoundingClientRect();
				pointerX = event.clientX;
				pointerY = event.clientY;

				if ( frame ) {
					return;
				}

				frame = window.requestAnimationFrame( () => {
					if ( ! effectsAllowed() || ! finePointer.matches || window.innerWidth <= 620 ) {
						frame = 0;
						return;
					}

					const xRatio = ( pointerX - rect.left ) / rect.width - 0.5;
					const yRatio = ( pointerY - rect.top ) / rect.height - 0.5;
					card.style.setProperty( '--tilt-x', `${ ( xRatio * 3.6 ).toFixed( 2 ) }deg` );
					card.style.setProperty( '--tilt-y', `${ ( yRatio * -3.6 ).toFixed( 2 ) }deg` );
					frame = 0;
				} );
			}, { passive: true } );

			card.addEventListener( 'pointerleave', () => {
				if ( frame ) {
					window.cancelAnimationFrame( frame );
					frame = 0;
				}
				rect = null;
				card.style.removeProperty( '--tilt-x' );
				card.style.removeProperty( '--tilt-y' );
			} );
		} );
	}

	function bindReadingProgress() {
		if ( ! progress ) {
			return;
		}

		let frame = 0;
		const update = () => {
			const scrollable = document.documentElement.scrollHeight - window.innerHeight;
			const ratio = scrollable > 0 ? Math.min( 1, Math.max( 0, window.scrollY / scrollable ) ) : 0;
			progress.style.transform = `scaleX(${ ratio })`;
			frame = 0;
		};

		const requestUpdate = () => {
			if ( ! frame ) {
				frame = window.requestAnimationFrame( update );
			}
		};

		window.addEventListener( 'scroll', requestUpdate, { passive: true } );
		window.addEventListener( 'resize', requestUpdate, { passive: true } );
		requestUpdate();
	}

	function bindClock() {
		if ( ! seoulClock ) {
			return;
		}

		const formatter = new Intl.DateTimeFormat( 'en-GB', {
			timeZone: 'Asia/Seoul',
			hour: '2-digit',
			minute: '2-digit',
			hour12: false,
		} );

		const update = () => {
			seoulClock.textContent = `SEOUL — ${ formatter.format( new Date() ) }`;
		};

		update();
		window.setInterval( update, 30000 );
	}

	if ( experienceControls ) {
		experienceControls.removeAttribute( 'inert' );
		experienceControls.setAttribute( 'aria-hidden', 'false' );
	}

	document.addEventListener( 'focusin', ( event ) => {
		event.target.closest?.( '.reveal' )?.classList.add( 'is-visible' );
	} );

	const initialMood = readStorage( 'odd-note-mood' );
	setMood( moods.includes( initialMood ) ? initialMood : moods[ 0 ] );

	if ( moodButton ) {
		moodButton.addEventListener( 'click', () => {
			const current = moods.indexOf( document.body.dataset.mood );
			setMood( moods[ ( current + 1 ) % moods.length ] );
		} );
	}

	if ( motionButton ) {
		motionButton.addEventListener( 'click', () => {
			if ( reducedMotion.matches ) {
				return;
			}

			userMotionOff = ! userMotionOff;
			writeStorage( 'odd-note-motion-off', userMotionOff ? '1' : '0' );
			updateMotionState();
		} );
	}

	reducedMotion.addEventListener( 'change', updateMotionState );
	finePointer.addEventListener( 'change', updateMotionState );
	bindMobileMenu();
	bindPointerStages();
	bindMagneticItems();
	bindTiltCards();
	bindReadingProgress();
	bindClock();
	updateMotionState();
} )();
