/**
 * Inline actions: runs data-kw-on<event>="..." handler attributes under a
 * strict Content-Security-Policy (no 'unsafe-inline', no 'unsafe-eval').
 *
 * Templates and page controllers used to write onclick="ctrl.save(5)". The
 * browser now refuses every inline handler, so they are written as
 * data-kw-onclick="ctrl.save(5)" and this file runs them. It never calls
 * eval/Function: each handler is parsed into a small syntax tree (a JS
 * subset: calls, member access, literals, assignments, if/return, logical
 * and comparison operators) and evaluated with these limits:
 *
 *  - dangerous globals and properties are unreachable (eval, Function,
 *    timers with strings, fetch/XHR/WebSocket, storage, cookie, innerHTML,
 *    outerHTML, insertAdjacentHTML, srcdoc, document.write, createElement,
 *    prototype tricks);
 *  - navigation (location, href, window.open) only to same-origin URLs;
 *  - script/frame/object elements cannot have src/href set;
 *  - events that fire without a user gesture (error, load, ...) may only
 *    touch the element itself, log, or call a short allowlist (image
 *    fallbacks, avatar helpers, print), so injected markup cannot trigger
 *    app actions on its own.
 *
 * Semantics follow inline handlers: `this` is the element, `event` the
 * event (currentTarget = the element), `return false` prevents the default,
 * and stopPropagation() stops the walk to ancestor handlers.
 *
 * Loaded first on every HTML surface with the page's CSP nonce, which it
 * publishes as window.KW_CSP_NONCE for the soft-navigation script loaders.
 */
(function () {
    'use strict';

    if (window.KingswayInlineActions) {
        return;
    }

    const current = document.currentScript;
    const NONCE = (current && (current.nonce || current.getAttribute('nonce'))) || '';
    try {
        Object.defineProperty(window, 'KW_CSP_NONCE', { value: NONCE, writable: false, configurable: false });
    } catch (_) {
        /* already defined */
    }

    const ATTR = 'data-kw-on';
    const EVENTS = [
        'click', 'dblclick', 'change', 'input', 'submit', 'reset', 'keydown', 'keyup', 'keypress',
        'mousedown', 'mouseup', 'mouseover', 'mouseout', 'mouseenter', 'mouseleave', 'focus', 'blur',
        'focusin', 'focusout', 'error', 'load', 'abort', 'contextmenu', 'paste', 'copy', 'cut',
        'drop', 'dragover', 'dragstart', 'dragend', 'dragenter', 'dragleave', 'wheel', 'scroll',
        'touchstart', 'touchend', 'toggle', 'search', 'select', 'invalid', 'ended', 'play', 'pause',
        'canplay', 'loadeddata', 'loadstart',
    ];
    // Events that do not bubble: only the target's own handler runs.
    const NON_BUBBLING = new Set(['focus', 'blur', 'mouseenter', 'mouseleave', 'error', 'load', 'abort',
        'scroll', 'toggle', 'invalid', 'ended', 'play', 'pause', 'canplay', 'loadeddata', 'loadstart']);
    // Events that can fire without the user doing anything.
    const AUTOMATIC = new Set(['error', 'load', 'abort', 'scroll', 'toggle', 'invalid', 'ended', 'play',
        'pause', 'canplay', 'loadeddata', 'loadstart']);
    const AUTOMATIC_CALLS = new Set([
        'console.log', 'console.info', 'console.warn', 'console.error', 'console.debug',
        'KingswayFileLifecycle.avatarUrl', 'KingswayFileLifecycle.assetUrl',
        'AllTeachersController.initialsNode',
        'window.opener.PrintManager.printElement', 'window.PrintManager.printElement',
        'window.close', 'window.print', 'print', 'close',
    ]);

    const DENY_GLOBALS = new Set([
        'eval', 'Function', 'AsyncFunction', 'GeneratorFunction', 'setTimeout', 'setInterval', 'setImmediate',
        'execScript', 'fetch', 'XMLHttpRequest', 'WebSocket', 'EventSource', 'Worker', 'SharedWorker',
        'importScripts', 'localStorage', 'sessionStorage', 'indexedDB', 'caches', 'cookieStore',
        'Reflect', 'Proxy', 'Object', 'globalThis', 'self', 'frames', 'top', 'parent', 'DOMParser',
        'Range', 'Blob', 'URL', 'webkitURL', 'trustedTypes', 'navigator',
    ]);
    const DENY_PROPS = new Set([
        'constructor', '__proto__', 'prototype', '__defineGetter__', '__defineSetter__',
        '__lookupGetter__', '__lookupSetter__', 'cookie', 'innerHTML', 'outerHTML', 'insertAdjacentHTML',
        'createContextualFragment', 'srcdoc', 'write', 'writeln', 'eval', 'Function', 'setTimeout',
        'setInterval', 'fetch', 'XMLHttpRequest', 'sendBeacon', 'execCommand', 'postMessage',
        'importScripts', 'localStorage', 'sessionStorage', 'indexedDB', 'createElement', 'createElementNS',
        'setAttributeNS', 'setHTMLUnsafe', 'parseHTMLUnsafe', 'call', 'apply', 'bind', 'Reflect', 'Proxy',
        'defineProperty', 'defineProperties', 'setPrototypeOf', 'cookieStore', 'serviceWorker',
        'registerProtocolHandler',
    ]);
    const URL_PROPS = new Set(['href', 'src', 'action', 'formAction', 'location', 'data', 'poster', 'background']);
    const UNSAFE_SRC_TAGS = new Set(['SCRIPT', 'IFRAME', 'FRAME', 'OBJECT', 'EMBED', 'BASE', 'LINK', 'FORM']);

    class HandlerError extends Error {}

    /* ---------------- tokenizer ---------------- */

    const PUNCT = ['===', '!==', '?.', '??', '&&', '||', '==', '!=', '<=', '>=', '=>',
        '(', ')', '[', ']', '{', '}', ',', ';', '.', '?', ':', '<', '>', '=', '!', '+', '-', '*', '/', '%'];

    function tokenize(src) {
        const out = [];
        let i = 0;
        const n = src.length;
        while (i < n) {
            const c = src[i];
            if (/\s/.test(c)) { i++; continue; }
            if (c === '/' && src[i + 1] === '/') { while (i < n && src[i] !== '\n') i++; continue; }
            if (c === '/' && src[i + 1] === '*') { const e = src.indexOf('*/', i + 2); i = e < 0 ? n : e + 2; continue; }
            if (/[0-9]/.test(c) || (c === '.' && /[0-9]/.test(src[i + 1] || ''))) {
                const m = /^(?:0[xX][0-9a-fA-F]+|\d*\.?\d+(?:[eE][+-]?\d+)?)/.exec(src.slice(i));
                out.push({ t: 'num', v: Number(m[0]) });
                i += m[0].length;
                continue;
            }
            if (c === '"' || c === "'" || c === '`') {
                let j = i + 1;
                let s = '';
                while (j < n && src[j] !== c) {
                    if (c === '`' && src[j] === '$' && src[j + 1] === '{') {
                        throw new HandlerError('template interpolation not supported');
                    }
                    if (src[j] === '\\') {
                        const e = src[j + 1];
                        const map = { n: '\n', t: '\t', r: '\r', b: '\b', f: '\f', v: '\v', 0: '\0' };
                        if (e === 'u') {
                            s += String.fromCharCode(parseInt(src.substr(j + 2, 4), 16));
                            j += 6;
                            continue;
                        }
                        if (e === 'x') {
                            s += String.fromCharCode(parseInt(src.substr(j + 2, 2), 16));
                            j += 4;
                            continue;
                        }
                        s += Object.prototype.hasOwnProperty.call(map, e) ? map[e] : e;
                        j += 2;
                        continue;
                    }
                    s += src[j++];
                }
                if (j >= n) throw new HandlerError('unterminated string');
                out.push({ t: 'str', v: s });
                i = j + 1;
                continue;
            }
            if (/[A-Za-z_$]/.test(c)) {
                const m = /^[A-Za-z_$][\w$]*/.exec(src.slice(i));
                out.push({ t: 'id', v: m[0] });
                i += m[0].length;
                continue;
            }
            const p = PUNCT.find((x) => src.startsWith(x, i));
            if (!p) throw new HandlerError('unexpected character ' + c);
            // `a?.5:b` is a conditional, not optional chaining.
            if (p === '?.' && /[0-9]/.test(src[i + 2] || '')) {
                out.push({ t: 'p', v: '?' });
                i += 1;
                continue;
            }
            out.push({ t: 'p', v: p });
            i += p.length;
        }
        out.push({ t: 'eof' });
        return out;
    }

    /* ---------------- parser ---------------- */

    function parse(src) {
        const toks = tokenize(src);
        let k = 0;
        const peek = (o = 0) => toks[k + o];
        const isP = (v, o = 0) => peek(o).t === 'p' && peek(o).v === v;
        const isId = (v) => peek().t === 'id' && peek().v === v;
        const eat = (v) => {
            if (!isP(v)) throw new HandlerError('expected ' + v);
            k++;
        };

        function program() {
            const body = [];
            while (peek().t !== 'eof') {
                if (isP(';')) { k++; continue; }
                body.push(statement());
            }
            return { type: 'block', body };
        }

        function statement() {
            if (isP('{')) {
                k++;
                const body = [];
                while (!isP('}')) {
                    if (peek().t === 'eof') throw new HandlerError('unterminated block');
                    if (isP(';')) { k++; continue; }
                    body.push(statement());
                }
                k++;
                return { type: 'block', body };
            }
            if (isId('if')) {
                k++;
                eat('(');
                const test = expression();
                eat(')');
                const then = statement();
                let otherwise = null;
                if (isP(';') && peek(1).t === 'id' && peek(1).v === 'else') k++;
                if (isId('else')) { k++; otherwise = statement(); }
                return { type: 'if', test, then, otherwise };
            }
            if (isId('return')) {
                k++;
                if (isP(';') || isP('}') || peek().t === 'eof') return { type: 'return', arg: null };
                return { type: 'return', arg: expression() };
            }
            const expr = expression();
            return { type: 'expr', expr };
        }

        function expression() {
            const left = conditional();
            if (isP('=')) {
                k++;
                if (left.type !== 'member' && left.type !== 'id') throw new HandlerError('invalid assignment');
                return { type: 'assign', target: left, value: expression() };
            }
            return left;
        }

        function conditional() {
            const test = nullish();
            if (isP('?')) {
                k++;
                const then = expression();
                eat(':');
                return { type: 'cond', test, then, otherwise: expression() };
            }
            return test;
        }

        function binaryLevel(next, ops) {
            return function level() {
                let left = next();
                while (peek().t === 'p' && ops.includes(peek().v)) {
                    const op = toks[k++].v;
                    left = { type: 'bin', op, left, right: next() };
                }
                return left;
            };
        }

        const multiplicative = binaryLevel(unary, ['*', '/', '%']);
        const additive = binaryLevel(multiplicative, ['+', '-']);
        const relational = binaryLevel(additive, ['<', '>', '<=', '>=']);
        const equality = binaryLevel(relational, ['===', '!==', '==', '!=']);
        const and = binaryLevel(equality, ['&&']);
        const or = binaryLevel(and, ['||']);
        const nullish = binaryLevel(or, ['??']);

        function unary() {
            if (isP('!') || isP('-') || isP('+')) {
                const op = toks[k++].v;
                return { type: 'unary', op, arg: unary() };
            }
            if (isId('typeof') || isId('void')) {
                const op = toks[k++].v;
                return { type: 'unary', op, arg: unary() };
            }
            return postfix();
        }

        function args() {
            const list = [];
            eat('(');
            while (!isP(')')) {
                list.push(expression());
                if (isP(',')) k++;
                else if (!isP(')')) throw new HandlerError('expected , or )');
            }
            k++;
            return list;
        }

        function postfix() {
            let node = primary();
            for (;;) {
                if (isP('.')) {
                    k++;
                    if (peek().t !== 'id') throw new HandlerError('expected property');
                    node = { type: 'member', object: node, prop: { type: 'lit', value: toks[k++].v }, optional: false };
                } else if (isP('?.')) {
                    k++;
                    if (isP('(')) {
                        node = { type: 'call', callee: node, args: args(), optional: true };
                    } else if (isP('[')) {
                        k++;
                        const prop = expression();
                        eat(']');
                        node = { type: 'member', object: node, prop, optional: true };
                    } else {
                        if (peek().t !== 'id') throw new HandlerError('expected property');
                        node = { type: 'member', object: node, prop: { type: 'lit', value: toks[k++].v }, optional: true };
                    }
                } else if (isP('[')) {
                    k++;
                    const prop = expression();
                    eat(']');
                    node = { type: 'member', object: node, prop, optional: false };
                } else if (isP('(')) {
                    node = { type: 'call', callee: node, args: args(), optional: false };
                } else {
                    return node;
                }
            }
        }

        function primary() {
            const t = peek();
            if (t.t === 'num' || t.t === 'str') { k++; return { type: 'lit', value: t.v }; }
            if (t.t === 'id') {
                k++;
                switch (t.v) {
                    case 'true': return { type: 'lit', value: true };
                    case 'false': return { type: 'lit', value: false };
                    case 'null': return { type: 'lit', value: null };
                    case 'undefined': return { type: 'lit', value: undefined };
                    case 'NaN': return { type: 'lit', value: NaN };
                    case 'Infinity': return { type: 'lit', value: Infinity };
                    case 'this': return { type: 'this' };
                    case 'new': case 'function': case 'class': case 'import': case 'with':
                    case 'delete': case 'yield': case 'await': case 'super': case 'var': case 'let': case 'const':
                        throw new HandlerError('unsupported keyword ' + t.v);
                    default: return { type: 'id', name: t.v };
                }
            }
            if (isP('(')) {
                k++;
                const e = expression();
                eat(')');
                return e;
            }
            if (isP('[')) {
                k++;
                const items = [];
                while (!isP(']')) {
                    items.push(expression());
                    if (isP(',')) k++;
                    else if (!isP(']')) throw new HandlerError('expected , or ]');
                }
                k++;
                return { type: 'array', items };
            }
            if (isP('{')) {
                k++;
                const props = [];
                while (!isP('}')) {
                    const key = peek();
                    if (key.t !== 'id' && key.t !== 'str' && key.t !== 'num') throw new HandlerError('bad key');
                    k++;
                    let value;
                    if (isP(':')) { k++; value = expression(); } else if (key.t === 'id') { value = { type: 'id', name: key.v }; } else {
                        throw new HandlerError('expected :');
                    }
                    props.push({ key: String(key.v), value });
                    if (isP(',')) k++;
                    else if (!isP('}')) throw new HandlerError('expected , or }');
                }
                k++;
                return { type: 'object', props };
            }
            throw new HandlerError('unexpected token');
        }

        const tree = program();
        return tree;
    }

    /* ---------------- evaluator ---------------- */

    const RETURN = Symbol('return');
    const cache = new Map();

    function staticPath(node) {
        if (node.type === 'id') return node.name;
        if (node.type === 'this') return 'this';
        if (node.type === 'member' && node.prop.type === 'lit' && typeof node.prop.value === 'string') {
            const base = staticPath(node.object);
            return base === null ? null : base + '.' + node.prop.value;
        }
        if (node.type === 'call') {
            const base = staticPath(node.callee);
            return base === null ? null : base + '()';
        }
        return null;
    }

    function rootedAtThis(node) {
        while (node && (node.type === 'member' || node.type === 'call')) {
            node = node.type === 'member' ? node.object : node.callee;
        }
        return node && node.type === 'this';
    }

    function sameOrigin(value) {
        const raw = String(value ?? '').trim();
        if (raw === '' || raw.startsWith('#')) return true;
        try {
            const url = new URL(raw, document.baseURI);
            return url.origin === window.location.origin && (url.protocol === 'https:' || url.protocol === 'http:');
        } catch (_) {
            return false;
        }
    }

    const FORBIDDEN_FUNCTIONS = new Set([
        window.eval, window.Function, window.setTimeout, window.setInterval, window.fetch,
        window.XMLHttpRequest, document.write, document.writeln, document.createElement,
        window.postMessage,
    ].filter(Boolean));

    function checkProp(name) {
        const p = String(name);
        if (DENY_PROPS.has(p)) throw new HandlerError('blocked property ' + p);
        return p;
    }

    function evaluate(tree, el, ev, automatic) {
        const ctx = { el, ev, automatic };
        try {
            const result = run(tree, ctx);
            return result && result[RETURN] ? result.value : undefined;
        } finally {
            ctx.el = null;
        }
    }

    function run(node, ctx) {
        switch (node.type) {
            case 'block':
                for (const s of node.body) {
                    const r = run(s, ctx);
                    if (r && r[RETURN]) return r;
                }
                return undefined;
            case 'if':
                if (value(node.test, ctx)) return run(node.then, ctx);
                return node.otherwise ? run(node.otherwise, ctx) : undefined;
            case 'return':
                return { [RETURN]: true, value: node.arg ? value(node.arg, ctx) : undefined };
            case 'expr':
                value(node.expr, ctx);
                return undefined;
            default:
                throw new HandlerError('bad statement');
        }
    }

    function resolveId(name, ctx) {
        if (name === 'event') return ctx.ev;
        if (DENY_GLOBALS.has(name) || DENY_PROPS.has(name)) throw new HandlerError('blocked global ' + name);
        if (name === 'window') return window;
        if (name === 'document') return document;
        if (!(name in window)) throw new HandlerError(name + ' is not defined');
        return window[name];
    }

    function value(node, ctx) {
        switch (node.type) {
            case 'lit': return node.value;
            case 'this': return ctx.el;
            case 'id': return resolveId(node.name, ctx);
            case 'array': return node.items.map((x) => value(x, ctx));
            case 'object': {
                const o = {};
                for (const p of node.props) {
                    checkProp(p.key);
                    o[p.key] = value(p.value, ctx);
                }
                return o;
            }
            case 'member': {
                const obj = value(node.object, ctx);
                if (obj === null || obj === undefined) {
                    if (node.optional) return undefined;
                    throw new HandlerError('cannot read property of ' + obj);
                }
                const prop = checkProp(value(node.prop, ctx));
                const v = obj[prop];
                if (FORBIDDEN_FUNCTIONS.has(v)) throw new HandlerError('blocked function ' + prop);
                return v;
            }
            case 'unary': {
                const a = value(node.arg, ctx);
                switch (node.op) {
                    case '!': return !a;
                    case '-': return -a;
                    case '+': return +a;
                    case 'typeof': return typeof a;
                    case 'void': return undefined;
                }
                break;
            }
            case 'bin': {
                if (node.op === '&&') { const l = value(node.left, ctx); return l ? value(node.right, ctx) : l; }
                if (node.op === '||') { const l = value(node.left, ctx); return l ? l : value(node.right, ctx); }
                if (node.op === '??') { const l = value(node.left, ctx); return l ?? value(node.right, ctx); }
                const l = value(node.left, ctx);
                const r = value(node.right, ctx);
                switch (node.op) {
                    case '===': return l === r;
                    case '!==': return l !== r;
                    // eslint-disable-next-line eqeqeq
                    case '==': return l == r;
                    // eslint-disable-next-line eqeqeq
                    case '!=': return l != r;
                    case '<': return l < r;
                    case '>': return l > r;
                    case '<=': return l <= r;
                    case '>=': return l >= r;
                    case '+': return l + r;
                    case '-': return l - r;
                    case '*': return l * r;
                    case '/': return l / r;
                    case '%': return l % r;
                }
                break;
            }
            case 'cond':
                return value(node.test, ctx) ? value(node.then, ctx) : value(node.otherwise, ctx);
            case 'call':
                return call(node, ctx);
            case 'assign':
                return assign(node, ctx);
        }
        throw new HandlerError('unsupported expression');
    }

    function call(node, ctx) {
        if (ctx.automatic && !rootedAtThis(node.callee)) {
            const path = (staticPath(node.callee) || '').replace(/\(\)/g, '');
            if (!AUTOMATIC_CALLS.has(path)) {
                throw new HandlerError('call not allowed from automatic event: ' + (path || 'expression'));
            }
        }
        let thisArg;
        let fn;
        if (node.callee.type === 'member') {
            thisArg = value(node.callee.object, ctx);
            if (thisArg === null || thisArg === undefined) {
                if (node.callee.optional || node.optional) return undefined;
                throw new HandlerError('cannot call method of ' + thisArg);
            }
            const prop = checkProp(value(node.callee.prop, ctx));
            fn = thisArg[prop];
            if ((thisArg === window.location || thisArg instanceof Location) && (prop === 'assign' || prop === 'replace' || prop === 'reload')) {
                const target = node.args.length ? value(node.args[0], ctx) : '';
                if (!sameOrigin(target)) throw new HandlerError('navigation blocked');
                return fn.call(thisArg, target);
            }
            if (prop === 'setAttribute') {
                const args = node.args.map((a) => value(a, ctx));
                const name = String(args[0] || '').toLowerCase();
                if (name.startsWith('on') || name === 'srcdoc') {
                    throw new HandlerError('blocked attribute ' + name);
                }
                if (name.startsWith(ATTR) && ctx.automatic) throw new HandlerError('blocked attribute ' + name);
                if (['href', 'src', 'action', 'formaction', 'xlink:href', 'data'].includes(name)) {
                    if (thisArg && UNSAFE_SRC_TAGS.has(thisArg.tagName)) throw new HandlerError('blocked attribute on ' + thisArg.tagName);
                    if (!sameOrigin(args[1]) && name !== 'src') throw new HandlerError('navigation blocked');
                }
                return fn.apply(thisArg, args);
            }
        } else {
            thisArg = undefined;
            fn = value(node.callee, ctx);
        }
        if (fn === null || fn === undefined) {
            if (node.optional) return undefined;
            throw new HandlerError((staticPath(node.callee) || 'value') + ' is not a function');
        }
        if (typeof fn !== 'function') throw new HandlerError((staticPath(node.callee) || 'value') + ' is not a function');
        if (FORBIDDEN_FUNCTIONS.has(fn)) throw new HandlerError('blocked function');
        const args = node.args.map((a) => value(a, ctx));
        if (fn === window.open) {
            if (args.length && !sameOrigin(args[0])) throw new HandlerError('navigation blocked');
            return window.open(...args);
        }
        return fn.apply(thisArg, args);
    }

    function assign(node, ctx) {
        const v = value(node.value, ctx);
        if (node.target.type === 'id') {
            throw new HandlerError('assignment to globals is not allowed');
        }
        if (ctx.automatic && !rootedAtThis(node.target)) {
            throw new HandlerError('automatic events may only change their own element');
        }
        const obj = value(node.target.object, ctx);
        if (obj === null || obj === undefined) throw new HandlerError('cannot set property of ' + obj);
        const prop = checkProp(value(node.target.prop, ctx));
        if (/^on[a-z]+$/.test(prop)) {
            // Only `this.onerror = null` style "run once" resets are meaningful.
            if (v !== null && v !== undefined) throw new HandlerError('assigning event handlers is not allowed');
            if (obj instanceof Element) obj.removeAttribute(ATTR + prop.slice(2));
            obj[prop] = null;
            return v;
        }
        if (obj === window && prop === 'location') {
            if (!sameOrigin(v)) throw new HandlerError('navigation blocked');
        }
        if (URL_PROPS.has(prop)) {
            if (obj instanceof Element && UNSAFE_SRC_TAGS.has(obj.tagName)) {
                throw new HandlerError('blocked property on ' + obj.tagName);
            }
            const isLocation = obj === window.location || (typeof Location !== 'undefined' && obj instanceof Location);
            const navigates = isLocation || prop === 'location' || prop === 'action' || prop === 'formAction' ||
                (prop === 'href' && obj instanceof Element);
            if (navigates && !sameOrigin(v)) throw new HandlerError('navigation blocked');
            if (/^\s*javascript:/i.test(String(v ?? ''))) throw new HandlerError('javascript: URL blocked');
        }
        obj[prop] = v;
        return v;
    }

    /* ---------------- dispatcher ---------------- */

    function compile(source) {
        let entry = cache.get(source);
        if (!entry) {
            try {
                entry = { tree: parse(source) };
            } catch (error) {
                entry = { error };
            }
            if (cache.size > 2000) cache.clear();
            cache.set(source, entry);
        }
        return entry;
    }

    function report(type, el, source, error) {
        const where = el && el.tagName ? el.tagName.toLowerCase() + (el.id ? '#' + el.id : '') : 'element';
        console.warn('[inline-actions] ' + type + ' handler on ' + where + ' failed: ' +
            (error && error.message ? error.message : error) + ' :: ' + String(source).slice(0, 160));
    }

    function runHandler(type, el, ev, state) {
        const source = el.getAttribute(ATTR + type);
        if (!source) return;
        const entry = compile(source);
        if (entry.error) {
            report(type, el, source, entry.error);
            return;
        }
        const proxy = new Proxy(ev, {
            get(target, prop) {
                if (prop === 'currentTarget') return el;
                if (prop === 'stopPropagation') return () => { state.stop = true; };
                if (prop === 'stopImmediatePropagation') return () => { state.stop = true; state.immediate = true; };
                if (prop === 'preventDefault') return () => target.preventDefault();
                const v = Reflect.get(target, prop, target);
                return typeof v === 'function' ? v.bind(target) : v;
            },
        });
        let result;
        try {
            result = evaluate(entry.tree, el, proxy, AUTOMATIC.has(type));
        } catch (error) {
            report(type, el, source, error);
            return;
        }
        if (result === false) ev.preventDefault();
        if (state.stop) {
            // Inline semantics: the element's own listeners still run, its
            // ancestors' do not. Stop the real event when it reaches `el`.
            const stopper = (e) => (state.immediate ? e.stopImmediatePropagation() : e.stopPropagation());
            el.addEventListener(type, stopper, { once: true, capture: state.immediate });
            setTimeout(() => el.removeEventListener(type, stopper, { capture: state.immediate }), 0);
        }
    }

    function dispatch(ev) {
        const type = ev.type;
        const name = ATTR + type;
        const state = { stop: false, immediate: false };
        if (NON_BUBBLING.has(type)) {
            let target = ev.target;
            if (type === 'load' && (target === document || target === window)) target = document.body;
            if (target instanceof Element && target.hasAttribute(name)) runHandler(type, target, ev, state);
            return;
        }
        const path = typeof ev.composedPath === 'function' ? ev.composedPath() : [];
        for (const node of path) {
            if (!(node instanceof Element)) continue;
            if (node.hasAttribute(name)) {
                runHandler(type, node, ev, state);
                if (state.stop) break;
            }
        }
    }

    for (const type of EVENTS) {
        window.addEventListener(type, dispatch, true);
    }

    window.KingswayInlineActions = Object.freeze({
        // Exposed for tests and for code that fires a handler programmatically.
        parse,
        run(el, type, ev) {
            runHandler(type, el, ev || new Event(type), { stop: false, immediate: false });
        },
        nonce: NONCE,
    });
})();
